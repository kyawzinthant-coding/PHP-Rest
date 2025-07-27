<?php

namespace App\Repository\Dashboard;

use App\Core\Database;
use PDO;

class DashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gets the four main KPI (Key Performance Indicator) stats.
     */
    public function getKpiStats(): array
    {
        $sql = "
            SELECT 
                (SELECT SUM(total_amount) FROM Orders) as total_revenue,
                (SELECT COUNT(*) FROM Orders) as total_orders,
                (SELECT COUNT(*) FROM Users WHERE role = 'customer') as total_customers,
                (SELECT COUNT(*) FROM Products WHERE is_active = 1) as total_products
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Gets the top 10 customers based on their total spending.
     */
    public function getTopCustomers(): array
    {
        $sql = "
            SELECT 
                u.name, 
                u.email, 
                SUM(o.total_amount) as total_spent
            FROM Orders o
            JOIN Users u ON o.user_id = u.id
            GROUP BY u.id, u.name, u.email
            ORDER BY total_spent DESC
            LIMIT 10
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gets the 5 most recent orders.
     */
    public function getRecentOrders(): array
    {
        $sql = "
            SELECT 
                order_number, 
                shipping_customer_name, 
                shipping_customer_email, 
                total_amount, 
                status, 
                created_at 
            FROM Orders 
            ORDER BY created_at DESC 
            LIMIT 5
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
