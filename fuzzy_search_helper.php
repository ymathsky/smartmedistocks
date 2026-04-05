<?php
/**
 * fuzzy_search_helper.php
 * Reusable fuzzy matching and search functions for items/medicines
 */

/**
 * Finds items matching a query using both exact/partial matching and fuzzy matching.
 * Returns exact matches first, then fuzzy suggestions if no exact matches found.
 * 
 * @param mysqli $conn Database connection
 * @param string $query The search query (medicine name or code)
 * @param int $exact_limit Max results from exact/partial match
 * @param int $fuzzy_limit Max results from fuzzy match
 * @return array ['exact' => [...], 'suggestions' => [...], 'query' => $query]
 */
function fuzzy_search_items($conn, $query, $exact_limit = 10, $fuzzy_limit = 3) {
    $result = [
        'exact' => [],
        'suggestions' => [],
        'query' => $query
    ];
    
    // 1. Try exact/partial match first (LIKE search)
    $search_pattern = '%' . $conn->real_escape_string($query) . '%';
    
    $sql = "
        SELECT
            i.item_id,
            i.name,
            i.item_code,
            i.category,
            i.unit_cost,
            COALESCE(SUM(b.quantity), 0) AS current_stock
        FROM items i
        LEFT JOIN item_batches b ON i.item_id = b.item_id
        WHERE i.name LIKE ? OR i.item_code LIKE ?
        GROUP BY i.item_id, i.name, i.item_code, i.category, i.unit_cost
        ORDER BY i.name ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    $stmt->bind_param('ssi', $search_pattern, $search_pattern, $exact_limit);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    $exact_result = $stmt->get_result();
    if (!$exact_result) {
        throw new Exception('Get result error: ' . $stmt->error);
    }
    
    while ($row = $exact_result->fetch_assoc()) {
        $result['exact'][] = $row;
    }
    $stmt->close();
    
    // 2. If no exact matches, try fuzzy matching using Levenshtein distance
    if (empty($result['exact'])) {
        $all_items_sql = "
            SELECT
                i.item_id,
                i.name,
                i.item_code,
                i.category,
                i.unit_cost,
                COALESCE(SUM(b.quantity), 0) AS current_stock
            FROM items i
            LEFT JOIN item_batches b ON i.item_id = b.item_id
            GROUP BY i.item_id, i.name, i.item_code, i.category, i.unit_cost
        ";
        
        $all_items_result = $conn->query($all_items_sql);
        
        if (!$all_items_result) {
            throw new Exception('Database query error: ' . $conn->error);
        }
        
        $item_distances = [];
        $query_lower = strtolower($query);
        
        while ($item = $all_items_result->fetch_assoc()) {
            $name_distance = levenshtein($query_lower, strtolower($item['name']));
            $code_distance = levenshtein($query_lower, strtolower($item['item_code']));
            $min_distance = min($name_distance, $code_distance);
            
            // Dynamic threshold based on query length
            $threshold = max(3, min(strlen($query), 5));
            
            if ($min_distance <= $threshold) {
                $item['similarity_score'] = $min_distance;
                $item_distances[] = $item;
            }
        }
        
        // Sort by similarity score (lowest = best match)
        usort($item_distances, function($a, $b) {
            return $a['similarity_score'] - $b['similarity_score'];
        });
        
        // Take top N suggestions
        $result['suggestions'] = array_slice($item_distances, 0, $fuzzy_limit);
    }
    
    return $result;
}

/**
 * Formats a single item result for display in chat/UI
 * 
 * @param array $item The item data
 * @return array Formatted item with display fields
 */
function format_item_for_display($item) {
    $stock = (int)($item['current_stock'] ?? 0);
    
    if ($stock > 10) {
        $status = 'Available';
        $status_color = '#16a34a';
        $status_icon = '✓';
    } elseif ($stock > 0) {
        $status = 'Limited';
        $status_color = '#ca8a04';
        $status_icon = '⚠';
    } else {
        $status = 'Out of stock';
        $status_color = '#dc2626';
        $status_icon = '✗';
    }
    
    return [
        'name' => htmlspecialchars($item['name']),
        'code' => htmlspecialchars($item['item_code']),
        'stock' => $stock,
        'status' => $status,
        'status_color' => $status_color,
        'status_icon' => $status_icon,
        'category' => htmlspecialchars($item['category'] ?? 'N/A'),
        'unit_cost' => isset($item['unit_cost']) ? number_format($item['unit_cost'], 2) : 'N/A'
    ];
}

/**
 * Generates HTML for exact match result
 * 
 * @param array $item The formatted item
 * @return string HTML
 */
function generate_exact_match_html($item) {
    return "<strong>{$item['name']}</strong> ({$item['code']}) &mdash; " .
           "<span style='color:{$item['status_color']};font-weight:600;'>{$item['status_icon']} {$item['status']}</span><br>" .
           "<small style='color:#6b7280;'>Category: {$item['category']} | Unit Cost: ₱{$item['unit_cost']}</small>";
}

/**
 * Generates HTML for suggestion result
 * 
 * @param array $item The formatted item
 * @return string HTML
 */
function generate_suggestion_html($item) {
    return "<div style='margin:6px 0; padding:8px; background:#f0f9ff; border-radius:6px; border-left:3px solid #3b82f6;'>" .
           "<strong style='cursor:pointer; color:#1e40af;'>{$item['name']}</strong> " .
           "<small style='color:#9ca3af;'>({$item['code']})</small><br>" .
           "<small style='color:#6b7280;'>Category: {$item['category']} | Stock: " .
           "<span style='color:{$item['status_color']};'>●</span> {$item['status']}</small>" .
           "</div>";
}
?>
