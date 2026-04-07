<?php

require_once __DIR__ . '/../../app/bootstrap.php';

require_roles(['admin', 'staff']);
$pdo = db();

$topSellingSql = 'SELECT i.item_name, SUM(sm.converted_base_qty) qty
                  FROM stock_movements sm
                  JOIN inventory_items i ON i.id = sm.item_id
                  WHERE sm.movement_type = "sale"
                  GROUP BY sm.item_id
                  ORDER BY qty DESC
                  LIMIT 5';
$topSelling = $pdo->query($topSellingSql)->fetchAll();

$topProfitSql = 'SELECT i.item_name,
                 SUM(CASE WHEN sm.movement_type = "sale" THEN sm.total_amount ELSE 0 END) -
                 SUM(CASE WHEN sm.movement_type = "purchase" THEN sm.total_amount ELSE 0 END) AS profit
                 FROM stock_movements sm
                 JOIN inventory_items i ON i.id = sm.item_id
                 GROUP BY sm.item_id
                 ORDER BY profit DESC
                 LIMIT 5';
$topProfit = $pdo->query($topProfitSql)->fetchAll();

json_response([
    'ok' => true,
    'top_selling' => $topSelling,
    'top_profitable' => $topProfit
]);
