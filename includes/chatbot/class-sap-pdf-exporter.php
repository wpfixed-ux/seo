<?php
/**
 * PDF Exporter for catalog
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_PDF_Exporter {

    /**
     * Export catalog to PDF
     *
     * @param string|null $category_slug Filter by category
     * @param int|null $producer_id Filter by producer
     */
    public function export($category_slug = null, $producer_id = null) {
        global $wpdb;

        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $producers_table = $wpdb->prefix . 'sap_producers';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        // Build query
        $where = array("p.status = 'active'", "pr.status = 'active'");
        $params = array();

        if ($category_slug) {
            $where[] = "c.slug = %s";
            $params[] = $category_slug;
        }

        if ($producer_id) {
            $where[] = "p.producer_id = %d";
            $params[] = $producer_id;
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT p.*, pr.name as producer_name, pr.city, pr.contact_phone, pr.contact_telegram,
                       c.name as category_name, c.icon as category_icon
                FROM $products_table p
                LEFT JOIN $producers_table pr ON p.producer_id = pr.id
                LEFT JOIN $categories_table c ON p.category_id = c.id
                WHERE $where_clause
                ORDER BY c.sort_order, c.name, p.name";

        if (!empty($params)) {
            $products = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $products = $wpdb->get_results($sql, ARRAY_A);
        }

        // Generate PDF
        $this->generate_pdf($products, $category_slug);
    }

    /**
     * Generate PDF document
     *
     * @param array $products Products data
     * @param string|null $category_slug Category filter
     */
    private function generate_pdf($products, $category_slug = null) {
        // Check for TCPDF or FPDF
        if (class_exists('TCPDF')) {
            $this->generate_with_tcpdf($products, $category_slug);
        } else {
            // Fallback to simple HTML to PDF
            $this->generate_html_pdf($products, $category_slug);
        }
    }

    /**
     * Generate PDF using TCPDF
     */
    private function generate_with_tcpdf($products, $category_slug) {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');

        $pdf->SetCreator('Craft Catalog');
        $pdf->SetAuthor('Craft Catalog Bot');
        $pdf->SetTitle('Каталог крафтовых товаров');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->SetDefaultMonospacedFont('dejavusansmono');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 25);

        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->Cell(0, 10, 'Каталог крафтовых товаров', 0, 1, 'C');

        if ($category_slug) {
            $pdf->SetFont('dejavusans', '', 12);
            $pdf->Cell(0, 8, 'Категория: ' . $category_slug, 0, 1, 'C');
        }

        $pdf->SetFont('dejavusans', '', 8);
        $pdf->Cell(0, 6, 'Дата: ' . date('d.m.Y H:i'), 0, 1, 'C');
        $pdf->Ln(5);

        // Group by category
        $grouped = array();
        foreach ($products as $product) {
            $cat = $product['category_name'] ?: 'Без категории';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = array();
            }
            $grouped[$cat][] = $product;
        }

        $pdf->SetFont('dejavusans', '', 9);

        foreach ($grouped as $category => $items) {
            // Category header
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 8, $category, 0, 1, 'L', true);
            $pdf->Ln(2);

            $pdf->SetFont('dejavusans', '', 9);

            foreach ($items as $product) {
                // Check if we need a new page
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                }

                // Product name
                $pdf->SetFont('dejavusans', 'B', 10);
                $stock = $product['in_stock'] ? '' : ' [Нет в наличии]';
                $pdf->Cell(0, 6, $product['name'] . $stock, 0, 1);

                // Price
                $pdf->SetFont('dejavusans', '', 9);
                if ($product['price'] > 0) {
                    $price = number_format($product['price'], 0, '.', ' ') . ' грн/' . $product['price_unit'];
                } else {
                    $price = 'Цена по запросу';
                }
                $pdf->Cell(0, 5, 'Цена: ' . $price, 0, 1);

                // Producer
                $location = $product['city'] ? ", {$product['city']}" : '';
                $pdf->Cell(0, 5, 'Производитель: ' . $product['producer_name'] . $location, 0, 1);

                // Contacts
                $contacts = array();
                if ($product['contact_telegram']) {
                    $contacts[] = 'Telegram: @' . $product['contact_telegram'];
                }
                if ($product['contact_phone']) {
                    $contacts[] = 'Тел: ' . $product['contact_phone'];
                }
                if (!empty($contacts)) {
                    $pdf->Cell(0, 5, implode(' | ', $contacts), 0, 1);
                }

                // Description (truncated)
                if ($product['description']) {
                    $desc = mb_substr(strip_tags($product['description']), 0, 200);
                    if (mb_strlen($product['description']) > 200) {
                        $desc .= '...';
                    }
                    $pdf->SetFont('dejavusans', '', 8);
                    $pdf->MultiCell(0, 4, $desc, 0, 'L');
                }

                $pdf->Ln(3);
            }

            $pdf->Ln(5);
        }

        // Output
        $filename = 'catalog_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D');
    }

    /**
     * Generate HTML-based PDF (fallback)
     */
    private function generate_html_pdf($products, $category_slug) {
        // Generate HTML
        $html = $this->generate_html($products, $category_slug);

        // Set headers for download
        $filename = 'catalog_' . date('Y-m-d') . '.html';

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo $html;
        exit;
    }

    /**
     * Generate HTML catalog
     */
    private function generate_html($products, $category_slug) {
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Каталог крафтовых товаров</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .date {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        .category {
            background: #f5f5f5;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .product {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .product-name {
            font-weight: bold;
            font-size: 12px;
        }
        .out-of-stock {
            color: #cc0000;
        }
        .price {
            color: #006600;
        }
        .producer {
            color: #666;
        }
        .contacts {
            font-size: 11px;
        }
        .description {
            font-size: 11px;
            color: #444;
            margin-top: 5px;
        }
        @media print {
            .product {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <h1>Каталог крафтовых товаров</h1>';

        if ($category_slug) {
            $html .= '<p style="text-align: center;">Категория: ' . esc_html($category_slug) . '</p>';
        }

        $html .= '<p class="date">Дата: ' . date('d.m.Y H:i') . '</p>';

        // Group by category
        $grouped = array();
        foreach ($products as $product) {
            $cat = $product['category_name'] ?: 'Без категории';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = array();
            }
            $grouped[$cat][] = $product;
        }

        foreach ($grouped as $category => $items) {
            $html .= '<div class="category">' . esc_html($category) . '</div>';

            foreach ($items as $product) {
                $html .= '<div class="product">';

                // Name
                $stock_class = $product['in_stock'] ? '' : ' out-of-stock';
                $stock_text = $product['in_stock'] ? '' : ' [Нет в наличии]';
                $html .= '<div class="product-name' . $stock_class . '">' .
                         esc_html($product['name']) . $stock_text . '</div>';

                // Price
                if ($product['price'] > 0) {
                    $price = number_format($product['price'], 0, '.', ' ') . ' грн/' . $product['price_unit'];
                } else {
                    $price = 'Цена по запросу';
                }
                $html .= '<div class="price">Цена: ' . esc_html($price) . '</div>';

                // Producer
                $location = $product['city'] ? ", {$product['city']}" : '';
                $html .= '<div class="producer">Производитель: ' .
                         esc_html($product['producer_name'] . $location) . '</div>';

                // Contacts
                $contacts = array();
                if ($product['contact_telegram']) {
                    $contacts[] = 'Telegram: @' . esc_html($product['contact_telegram']);
                }
                if ($product['contact_phone']) {
                    $contacts[] = 'Тел: ' . esc_html($product['contact_phone']);
                }
                if (!empty($contacts)) {
                    $html .= '<div class="contacts">' . implode(' | ', $contacts) . '</div>';
                }

                // Description
                if ($product['description']) {
                    $desc = mb_substr(strip_tags($product['description']), 0, 200);
                    if (mb_strlen($product['description']) > 200) {
                        $desc .= '...';
                    }
                    $html .= '<div class="description">' . esc_html($desc) . '</div>';
                }

                $html .= '</div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Export catalog for specific producer
     *
     * @param int $producer_id Producer ID
     */
    public function export_producer_catalog($producer_id) {
        $this->export(null, $producer_id);
    }
}
