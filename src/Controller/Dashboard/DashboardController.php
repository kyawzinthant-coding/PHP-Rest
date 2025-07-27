<?php

namespace App\Controller\Dashboard;

use App\Core\Request;
use App\Repository\Dashboard\DashboardRepository;

class DashboardController
{
    private DashboardRepository $dashboardRepository;

    public function __construct()
    {
        $this->dashboardRepository = new DashboardRepository();
    }

    /**
     * ADMIN-ONLY: Gathers all statistics for the main dashboard.
     */
    public function getStats(Request $request): void
    {
        $user = $request->getAttribute('user');
        if (!$user || $user->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: Admins only.']);
            return;
        }

        try {
            $kpiStats = $this->dashboardRepository->getKpiStats();
            $topCustomers = $this->dashboardRepository->getTopCustomers();
            $recentOrders = $this->dashboardRepository->getRecentOrders();

            // Assemble the final response object
            $dashboardData = [
                'kpi' => [
                    'totalRevenue' => (float)($kpiStats['total_revenue'] ?? 0),
                    'totalOrders' => (int)($kpiStats['total_orders'] ?? 0),
                    'totalCustomers' => (int)($kpiStats['total_customers'] ?? 0),
                    'totalProducts' => (int)($kpiStats['total_products'] ?? 0),
                ],
                'topCustomers' => $topCustomers,
                'recentOrders' => $recentOrders,
            ];

            echo json_encode(['status' => 'success', 'data' => $dashboardData]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve dashboard stats: ' . $e->getMessage()]);
        }
    }
}
