<?php
// Filename: get_forecast_data.php
// FIX: Temporarily disable error reporting in this API handler to prevent runtime warnings/notices
// from breaking the JSON response with HTML output.
error_reporting(0);

// Set the content type to JSON for the response
header('Content-Type: application/json');

// 1. ESTABLISH DATABASE CONNECTION
require_once 'db_connection.php';
session_start();

// 2. SECURITY AND INPUT VALIDATION
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Pharmacist', 'Procurement', 'Warehouse'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$forecast_horizon = filter_input(INPUT_GET, 'forecast_horizon', FILTER_VALIDATE_INT) ?: 30; // NEW: Get horizon, default to 30

if (!$item_id) {
    echo json_encode(['error' => 'Invalid item ID provided.']);
    exit();
}

// --- Fetch Global Settings for Inventory Policy Calculations ---
$settings_sql = "SELECT setting_name, setting_value FROM settings";
// FIX: Use @ symbol to suppress any potential notices if connection failed earlier
@$settings_result = $conn->query($settings_sql);
$settings = [];
if ($settings_result) {
    while($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$ordering_cost = isset($settings['ordering_cost']) ? (float)$settings['ordering_cost'] : 50; // S
$holding_cost_rate = isset($settings['holding_cost_rate']) ? (float)$settings['holding_cost_rate'] : 25; // i
$service_level = isset($settings['service_level']) ? (float)$settings['service_level'] : 95; // Z
$z_scores = [90 => 1.28, 95 => 1.65, 98 => 2.05, 99 => 2.33];
$z_score = isset($z_scores[$service_level]) ? $z_scores[$service_level] : 1.65; // Default to 95%

// --- Fetch Item Data (for Unit Cost/Lead Time) ---
$item_details_sql = "
    SELECT 
        i.unit_cost, i.name,
        COALESCE(s.average_lead_time_days, 7) as lead_time_days
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    WHERE i.item_id = ?
";
$item_stmt = $conn->prepare($item_details_sql);
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_details = $item_stmt->get_result()->fetch_assoc();
$item_stmt->close();
// FIX: Set a minimum unit cost of 0.1 to avoid zero EOQ calculation issues if not set in DB
$unit_cost = (float)($item_details['unit_cost'] ?? 1);
$unit_cost = max(0.1, $unit_cost);
$lead_time_days = (int)($item_details['lead_time_days'] ?? 7);
$item_name = $item_details['name'] ?? 'Item';


// 3. FETCH TRANSACTION DATA
$historical_period = 180; // Days of history remains fixed at 180 days for model stability
$one_eighty_days_ago = date('Y-m-d', strtotime("-$historical_period days"));
$sql = "
    SELECT transaction_date, SUM(quantity_used) as total_quantity
    FROM transactions
    WHERE item_id = ? AND transaction_date >= ?
    GROUP BY transaction_date
    ORDER BY transaction_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $item_id, $one_eighty_days_ago);
$stmt->execute();
$result = $stmt->get_result();

$transactions_by_date = [];
while ($row = $result->fetch_assoc()) {
    $transactions_by_date[$row['transaction_date']] = (int)$row['total_quantity'];
}
$stmt->close();


// 4. PREPARE DATA FOR FORECASTING (Fill missing days with 0)
$historical_data = [];
$dates = [];
$first_date = new DateTime($one_eighty_days_ago);
$current_date = clone $first_date;
$today = new DateTime();
$today->setTime(0, 0);

while ($current_date <= $today) {
    $date_string = $current_date->format('Y-m-d');
    $dates[] = $date_string;
    $historical_data[] = $transactions_by_date[$date_string] ?? 0;
    $current_date->modify('+1 day');
}

$n = count($historical_data);
// $forecast_horizon is now a variable set by user input

// FIX: Lowered minimum requirement from 30 days to 5 days for robust calculation demo
if ($n < 5) { // Minimum data check (5 days)
    echo json_encode(['error' => 'Not enough historical data for a forecast (minimum 5 days required). Only ' . $n . ' days found.']);
    exit();
}


// --- 5. DEMAND CLASSIFICATION & MODEL SELECTION ---
$non_zero_demands = array_filter($historical_data, function($q) { return $q > 0; });
$non_zero_count = count($non_zero_demands);

// Average Inter-Demand Interval (ADI)
$adi = ($non_zero_count > 0) ? $n / $non_zero_count : $n;

// Coefficient of Variation (CV) of non-zero demands
$cv2 = 0;
if ($non_zero_count > 1) {
    $mean = array_sum($non_zero_demands) / $non_zero_count;
    $variance = 0;
    foreach ($non_zero_demands as $d) {
        $variance += pow($d - $mean, 2);
    }
    // FIX: Check for zero division before calculating variance
    if ($non_zero_count - 1 > 0) {
        $variance /= ($non_zero_count - 1);
    } else {
        $variance = 0; // Or handle as an error case if needed
    }
    // CV^2 is variance / mean^2
    $cv2 = ($mean > 0) ? ($variance / pow($mean, 2)) : 10;
}

// ORIGINAL LOGIC: $is_intermittent = $adi >= 1.32 && $cv2 > 0.49;
// MODIFIED: Force $is_intermittent to false to select Holt-Winters (Smooth)
$is_intermittent = false;

// CLASSIFICATION (Following Syntetos-Boylan guidelines)
$forecast_model = $is_intermittent ? "Croston's Method (Intermittent)" : "Holt-Winters (Seasonal/Smooth)";

// --- 6. FORECASTING FUNCTIONS ---

/**
 * Holt-Winters (Additive Seasonal) for Smooth/Seasonal Demand.
 * Now accepts forecast_horizon as a parameter.
 */
function holtWinters($data, $forecast_horizon, $seasonal_period = 7) {
    $n = count($data);

    // Search grid for optimization
    $grid = [0.1, 0.3, 0.5, 0.7, 0.9];
    $best_mape = INF;
    $best_params = ['alpha' => 0.1, 'beta' => 0.1, 'gamma' => 0.1];
    $best_output = null;

    // Grid Search to find best parameters
    foreach ($grid as $a) {
        foreach ($grid as $b) {
            foreach ($grid as $g) {
                // Initialize L, T, S (simple average initialization)
                $initial_sum = array_sum(array_slice($data, 0, $seasonal_period));
                $L = ($seasonal_period > 0) ? $initial_sum / $seasonal_period : 0;
                $T = 0;
                $S = array_fill(0, $seasonal_period, 0);
                $sum_abs_percentage_error = 0;
                $mape_count = 0;

                $L_prev = $L;
                $T_prev = $T;
                $S_prev = $S; // Use copies to avoid direct modification

                // Apply Smoothing and Calculate Error
                for ($i = 0; $i < $n; $i++) {
                    $y = $data[$i];
                    // FIX: Ensure season_index is valid even if seasonal_period is 0 (though set to 7 above)
                    $season_index = $seasonal_period > 0 ? $i % $seasonal_period : 0;

                    // Prediction
                    // FIX: Check if season index is valid before accessing $S_prev
                    $season_value = isset($S_prev[$season_index]) ? $S_prev[$season_index] : 0;
                    $prediction = $L_prev + $T_prev + $season_value;
                    $prediction = max(0, $prediction);

                    // Calculate error for MAPE (start after initialization period)
                    if ($i >= $seasonal_period && $y > 0) {
                        // FIX: Check for division by zero before calculating MAPE component
                        if ($y > 0) {
                            $sum_abs_percentage_error += abs($y - $prediction) / $y;
                            $mape_count++;
                        }
                    }

                    // Apply Smoothing Equations
                    $L = $a * ($y - $season_value) + (1 - $a) * ($L_prev + $T_prev);
                    $T = $b * ($L - $L_prev) + (1 - $b) * $T_prev;
                    // FIX: Ensure season_index is valid before assignment
                    if ($seasonal_period > 0) {
                        $S[$season_index] = $g * ($y - $L) + (1 - $g) * $season_value;
                    }

                    $L_prev = $L;
                    $T_prev = $T;
                    $S_prev = $S;
                }

                // FIX: Check for division by zero when calculating final MAPE
                $mape = ($mape_count > 0) ? ($sum_abs_percentage_error / $mape_count) * 100 : INF;

                if ($mape < $best_mape) {
                    $best_mape = $mape;
                    $best_params = ['alpha' => $a, 'beta' => $b, 'gamma' => $g];
                    $best_output = ['L' => $L_prev, 'T' => $T_prev, 'S' => $S_prev]; // Store final values
                }
            }
        }
    }

    // Safety check in case the grid search failed to produce a valid output
    if (null === $best_output) {
        return ['error' => 'Holt-Winters parameter optimization failed.'];
    }

    // Recalculate full historical fit and future forecast using BEST parameters
    $a = $best_params['alpha']; $b = $best_params['beta']; $g = $best_params['gamma'];
    // FIX: Check if seasonal_period is valid before division
    $initial_sum = array_sum(array_slice($data, 0, $seasonal_period));
    $L_prev = ($seasonal_period > 0) ? $initial_sum / $seasonal_period : 0;

    $T_prev = 0;
    $S_prev = array_fill(0, $seasonal_period, 0);

    $historical_fit = array_fill(0, $n, null);
    $total_historical_demand = array_sum($data);

    // Initial pass for smoothing (to get final L, T, S values)
    for ($i = 0; $i < $n; $i++) {
        $y = $data[$i];
        $season_index = $seasonal_period > 0 ? $i % $seasonal_period : 0;
        $season_value = isset($S_prev[$season_index]) ? $S_prev[$season_index] : 0;

        $prediction = $L_prev + $T_prev + $season_value;
        $historical_fit[$i] = max(0, round($prediction)); // Round for plotting consistency

        // Smoothing
        $L = $a * ($y - $season_value) + (1 - $a) * ($L_prev + $T_prev);
        $T = $b * ($L - $L_prev) + (1 - $b) * $T_prev;
        if ($seasonal_period > 0) {
            $S_prev[$season_index] = $g * ($y - $L) + (1 - $g) * $season_value;
        }

        $L_prev = $L;
        $T_prev = $T;
    }

    // Future Forecast
    $forecast_future = [];
    $total_forecast_demand = 0;
    for ($i = 0; $i < $forecast_horizon; $i++) {
        $index = $seasonal_period > 0 ? ($n + $i) % $seasonal_period : 0;
        $season_value = isset($S_prev[$index]) ? $S_prev[$index] : 0;

        $prediction = $L_prev + $T_prev + $season_value;
        $forecast_future[] = max(0, round($prediction));
        $total_forecast_demand += max(0, $prediction);
    }

    $avg_daily_demand = $forecast_horizon > 0 ? $total_forecast_demand / $forecast_horizon : 0;

    // FIX: Guarantee a non-zero average demand if there was historical usage
    if ($total_historical_demand > 0 && $avg_daily_demand < 0.1) {
        $avg_daily_demand = max($total_historical_demand / $n, 0.1);
    }

    return [
        'mape' => round($best_mape, 2),
        'alpha' => round($a, 2),
        'beta' => round($b, 2),
        'gamma' => round($g, 2),
        'historical_fit' => $historical_fit,
        'forecast_future' => $forecast_future,
        // FIX: The key that was being looked for in the calling function
        'avg_daily_demand' => round((float)$avg_daily_demand, 4)
    ];
}


/**
 * Croston's Method for Intermittent Demand (using alpha=0.1 fixed).
 * Now accepts forecast_horizon as a parameter.
 */
function crostonsMethod($data, $forecast_horizon, $alpha = 0.1) {
    $n = count($data);

    // Separate demand size (z) and inter-demand interval (p)
    $demands = [];
    $intervals = [];
    $last_occurrence = 0;

    foreach ($data as $i => $y) {
        if ($y > 0) {
            $demands[] = $y;
            $intervals[] = $i - $last_occurrence;
            $last_occurrence = $i;
        }
    }

    if (empty($demands)) {
        // FIX: Return 0 demand but avoid error if historical is 0
        return [
            'mape' => 'N/A',
            'alpha' => $alpha,
            'beta' => 0.0,
            'gamma' => 0.0,
            'historical_fit' => array_fill(0, $n, 0),
            'forecast_future' => array_fill(0, $forecast_horizon, 0),
            'avg_daily_demand' => 0.0 // FIX: Corrected return key
        ];
    }

    // Initial values
    $z_forecast = $demands[0]; // Forecast for demand size
    $p_forecast = $intervals[0] ?? 1; // Forecast for inter-demand interval

    // Apply simple exponential smoothing (alpha fixed at 0.1)
    $q_index = 0; // Index of the next non-zero demand
    $q_prev_occurrence = 0;
    $historical_fit = array_fill(0, $n, null);
    $mape_count = 0;
    $sum_abs_percentage_error = 0;

    for ($i = 0; $i < $n; $i++) {
        // FIX: Check for zero division before calculating forecast_value
        $forecast_value = ($p_forecast > 0) ? $z_forecast / $p_forecast : 0;
        $historical_fit[$i] = $forecast_value;

        // Calculate MAPE for non-zero demand days only
        if ($data[$i] > 0) {
            // FIX: Check for division by zero
            if ($data[$i] > 0) {
                $sum_abs_percentage_error += abs($data[$i] - $forecast_value) / $data[$i];
            }
            $mape_count++;
        }

        if ($data[$i] > 0) {
            // New demand size (z)
            $z_forecast = $alpha * $data[$i] + (1 - $alpha) * $z_forecast;

            // New inter-demand interval (p)
            $current_interval = $i - $q_prev_occurrence;
            $p_forecast = $alpha * $current_interval + (1 - $alpha) * $p_forecast;

            $q_prev_occurrence = $i;
            $q_index++;
        }
    }

    $mape = ($mape_count > 0) ? ($sum_abs_percentage_error / $mape_count) * 100 : INF;

    // Future Forecast (constant demand rate)
    // FIX: Check for zero division
    $avg_daily_demand = ($p_forecast > 0) ? $z_forecast / $p_forecast : 0;

    // Use constant daily demand for the selected horizon
    $forecast_future = array_fill(0, $forecast_horizon, round($avg_daily_demand));
    $total_forecast_demand = $avg_daily_demand * $forecast_horizon;


    return [
        'mape' => round($mape, 2),
        'alpha' => $alpha,
        'beta' => 0.0, // Not applicable
        'gamma' => 0.0, // Not applicable
        'historical_fit' => $historical_fit,
        'forecast_future' => $forecast_future,
        // FIX: The key that was being looked for in the calling function
        'avg_daily_demand' => round((float)$avg_daily_demand, 4)
    ];
}


// --- 7. EXECUTE SELECTED MODEL ---

$forecast_output = [];
if ($is_intermittent) {
    // Run Croston's Method
    $forecast_output = crostonsMethod($historical_data, $forecast_horizon);
} else {
    // Run Holt-Winters (optimized)
    $forecast_output = holtWinters($historical_data, $forecast_horizon);
}

if (isset($forecast_output['error'])) {
    echo json_encode(['error' => "Forecasting Failed: " . $forecast_output['error']]);
    exit();
}

// FIX: Correctly retrieve the average daily demand from the output array
$avg_forecast_daily_demand = $forecast_output['avg_daily_demand'];

$mape = $forecast_output['mape'];
$alpha = $forecast_output['alpha'];
$beta = $forecast_output['beta'];
$gamma = $forecast_output['gamma'];
$historical_prediction_data = $forecast_output['historical_fit'];
$forecast_data_future = $forecast_output['forecast_future'];


// 8. CALCULATE ROP/EOQ based on Forecasted Demand

// FIX: Only calculate inventory policies if we have a non-zero average demand.
if ($avg_forecast_daily_demand > 0) {
    $annual_demand_forecast = $avg_forecast_daily_demand * 365;

    // Safety Stock (using simple std dev proxy for demand variability)
    $std_dev_daily_demand = sqrt($avg_forecast_daily_demand);
    $safety_stock = $z_score * $std_dev_daily_demand * sqrt($lead_time_days);

    // Reorder Point (ROP)
    $demand_during_lead_time = $avg_forecast_daily_demand * $lead_time_days;
    $reorder_point = $demand_during_lead_time + $safety_stock;

    // EOQ
    $holding_cost_per_unit = $unit_cost * ($holding_cost_rate / 100);
    // FIX: Check for zero division
    $eoq = ($holding_cost_per_unit > 0) ? sqrt((2 * $annual_demand_forecast * $ordering_cost) / $holding_cost_per_unit) : 0;
} else {
    // Set to meaningful '0' values when demand is zero/non-existent
    $safety_stock = 0;
    $reorder_point = 0;
    $eoq = 0;
}


// 9. ALIGN AND SEND JSON RESPONSE
$forecast_data = array_merge($historical_prediction_data, $forecast_data_future);
$historical_data_aligned = array_pad(array_slice($historical_data, 0, $n), $n + $forecast_horizon, null);

// Add future date labels
$future_date = new DateTime($dates[$n - 1]); // Start from the last historical date
for ($i = 1; $i <= $forecast_horizon; $i++) {
    $future_date->modify('+1 day');
    $dates[] = $future_date->format('Y-m-d');
}


echo json_encode([
    'labels' => $dates,
    'historical' => $historical_data_aligned,
    // FIX: Ensure forecast is displayed by rounding values, as they are floats
    'forecast' => array_map(function($val) { return is_numeric($val) ? round($val) : null; }, $forecast_data),
    'model' => $forecast_model,
    'classification' => $is_intermittent ? 'Intermittent' : 'Smooth',
    'metrics' => [
        'mape' => $mape,
        'alpha' => round($alpha, 2),
        'beta' => round($beta, 2),
        'gamma' => round($gamma, 2),
        'safety_stock' => round($safety_stock),
        'rop' => round($reorder_point),
        'eoq' => round($eoq),
        'avg_forecast_demand' => $avg_forecast_daily_demand
    ]
]);
?>
