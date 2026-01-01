import { usePage } from "@inertiajs/react";
import {
    IconBox,
    IconFolder,
    IconUsersPlus,
    IconShoppingCart,
    IconClockHour6,
    IconFileDescription,
    IconFileCertificate,
    IconChartArrowsVertical,
    IconChartBarPopular,
    IconUserBolt,
    IconUserShield,
    IconUsers,
    IconTable,
    IconCirclePlus,
    IconCreditCard,
    IconLayout2,
    IconBuildingWarehouse,
} from "@tabler/icons-react";
import hasAnyPermission from "./Permission";
import React from "react";

export default function Menu() {
    const { url } = usePage();

    const menuNavigation = [
        // =======================
        // OVERVIEW
        // =======================
        {
            title: "Overview",
            details: [
                {
                    title: "Dashboard",
                    href: route("dashboard"),
                    active: url === "/dashboard",
                    icon: <IconLayout2 size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["dashboard-access"]),
                },
            ],
        },

        // =======================
        // DATA MANAGEMENT
        // =======================
        {
            title: "Data Management",
            details: [
                {
                    title: "Kategori",
                    href: route("categories.index"),
                    active: url.startsWith("/dashboard/categories"),
                    icon: <IconFolder size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["categories-access"]),
                },
                {
                    title: "Produk",
                    href: route("products.index"),
                    active: url.startsWith("/dashboard/products"),
                    icon: <IconBox size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["products-access"]),
                },
                {
                    title: "Pelanggan",
                    href: route("customers.index"),
                    active: url.startsWith("/dashboard/customers"),
                    icon: <IconUsersPlus size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["customers-access"]),
                },
            ],
        },

        // =======================
        // TRANSAKSI
        // =======================
        {
            title: "Transaksi",
            details: [
                {
                    title: "Transaksi",
                    href: route("transactions.index"),
                    active: url === "/dashboard/transactions",
                    icon: <IconShoppingCart size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["transactions-access"]),
                },
                {
                    title: "Riwayat Transaksi",
                    href: route("transactions.history"),
                    active: url === "/dashboard/transactions/history",
                    icon: <IconClockHour6 size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["transactions-access"]),
                },
            ],
        },

        // =======================
        // PEMBELIAN
        // =======================
        {
            title: "Pembelian",
            details: [
                {
                    title: "Pembelian",
                    href: route("purchase.index"),
                    active: url.startsWith ("/dashboard/purchase"),
                    icon: <IconFileDescription size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["purchase-access"]),
                },
                {
                    title: "Tambah Pembelian",
                    href: route("purchase.create"),
                    active: url === "/dashboard/purchase/create",
                    icon: <IconCirclePlus size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["purchase-create"]),
                },
            ],
        },

        // =======================
        // LAPORAN
        // =======================
        {
            title: "Laporan",
            details: [
                {
                    title: "Laporan Penjualan",
                    href: route("reports.sales.index"),
                    active: url.startsWith("/dashboard/reports/sales"),
                    icon: <IconChartArrowsVertical size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["reports-access"]),
                },
                {
                    title: "Laporan Keuntungan",
                    href: route("reports.profits.index"),
                    active: url.startsWith("/dashboard/reports/profits"),
                    icon: <IconChartBarPopular size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["profits-access"]),
                },
                {
                    title: "Persediaan",
                    href: route("reports.inventories.index"),
                    active: url.startsWith("/dashboard/reports/inventories"),
                    icon: <IconBuildingWarehouse size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["inventories-access"]),
                },
            ],
        },

        // =======================
        // USER MANAGEMENT
        // =======================
        {
            title: "User Management",
            details: [
                {
                    title: "Hak Akses",
                    href: route("permissions.index"),
                    active: url.startsWith("/dashboard/permissions"),
                    icon: <IconUserBolt size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["permissions-access"]),
                },
                {
                    title: "Akses Group",
                    href: route("roles.index"),
                    active: url.startsWith("/dashboard/roles"),
                    icon: <IconUserShield size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["roles-access"]),
                },
                {
                    title: "Pengguna",
                    icon: <IconUsers size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["users-access"]),
                    subdetails: [
                        {
                            title: "Data Pengguna",
                            href: route("users.index"),
                            active: url.startsWith("/dashboard/users") && !url.includes("create"),
                            icon: <IconTable size={20} strokeWidth={1.5} />,
                            permissions: hasAnyPermission(["users-access"]),
                        },
                        {
                            title: "Tambah Data Pengguna",
                            href: route("users.create"),
                            active: url === "/dashboard/users/create",
                            icon: <IconCirclePlus size={20} strokeWidth={1.5} />,
                            permissions: hasAnyPermission(["users-create"]),
                        },
                    ],
                },
            ],
        },

        // =======================
        // PENGATURAN
        // =======================
        {
            title: "Pengaturan",
            details: [
                {
                    title: "Payment Gateway",
                    href: route("settings.payments.edit"),
                    active: url.startsWith("/dashboard/settings/payments"),
                    icon: <IconCreditCard size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(["payment-settings-access"]),
                },
            ],
        },
    ];

    return menuNavigation;
}
