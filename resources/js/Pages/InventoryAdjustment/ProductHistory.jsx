import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link, router } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconArrowLeft,
    IconFilter,
    IconFilterOff,
    IconPackage,
    IconArrowUp,
    IconArrowDown,
    IconDatabaseOff,
    IconPlus,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import Pagination from "@/Components/Dashboard/Pagination";
import { formatDateTime } from "@/Utils/DateHelper";

export default function ProductHistory({ product, adjustments, summary, types, filters }) {
    const [showFilters, setShowFilters] = useState(false);
    const [filterData, setFilterData] = useState({
        type: filters?.type || "",
        from: filters?.from || "",
        to: filters?.to || "",
    });

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilterData((prev) => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get(
            route("inventory-adjustments.product-history", product.id),
            filterData,
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const resetFilters = () => {
        const emptyFilters = {
            type: "",
            from: "",
            to: "",
        };
        setFilterData(emptyFilters);
        router.get(
            route("inventory-adjustments.product-history", product.id),
            {},
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const hasActiveFilters = Object.values(filterData).some((v) => v !== "");

    // Format currency
    const formatCurrency = (value) => {
        const num = Number(value);
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        }).format(isNaN(num) ? 0 : num);
    };

    // Get type badge
    const getTypeBadge = (type) => {
        const inTypes = ["in", "purchase", "return"];
        const isIncoming = inTypes.includes(type);

        return (
            <span
                className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                    isIncoming
                        ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        : "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                }`}
            >
                {isIncoming ? (
                    <IconArrowUp className="w-3 h-3" />
                ) : (
                    <IconArrowDown className="w-3 h-3" />
                )}
                {types[type] || type}
            </span>
        );
    };

    return (
        <DashboardLayout>
            <Head title={`Stock History - ${product.title}`} />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route("inventory-adjustments.index")}>
                            <Button variant="light" className="p-2" title="Kembali">
                                <IconArrowLeft className="w-5 h-5" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800 dark:text-white">
                                Riwayat Stok
                            </h1>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {product.title} ({product.barcode})
                            </p>
                        </div>
                    </div>
                    <Link href={route("inventory-adjustments.create")}>
                        <Button variant="primary" className="flex items-center gap-2">
                            <IconPlus className="w-4 h-4" />
                            <span>Adjustment</span>
                        </Button>
                    </Link>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Stok Saat Ini
                        </p>
                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {Number(summary.current_stock).toLocaleString("id-ID")}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Total Masuk
                        </p>
                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                            +{Number(summary.total_in).toLocaleString("id-ID")}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Total Keluar
                        </p>
                        <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                            -{Number(summary.total_out).toLocaleString("id-ID")}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Stok Inventory
                        </p>
                        <p className="text-2xl font-bold text-slate-800 dark:text-white">
                            {Number(summary.inventory_stock).toLocaleString("id-ID")}
                        </p>
                    </div>
                </div>

                {/* Product Info Card */}
                <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                    <div className="flex items-center gap-4">
                        <div className="w-16 h-16 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <IconPackage className="w-8 h-8 text-slate-400" />
                        </div>
                        <div className="flex-1">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white">
                                {product.title}
                            </h2>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Barcode: {product.barcode || "-"} •{" "}
                                {product.category?.name || "Uncategorized"}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Harga Beli
                            </p>
                            <p className="font-medium text-slate-800 dark:text-white">
                                {formatCurrency(product.buy_price)}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Harga Jual
                            </p>
                            <p className="font-medium text-slate-800 dark:text-white">
                                {formatCurrency(product.sell_price)}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filters & Table */}
                <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                    <div className="p-4 border-b border-slate-200 dark:border-slate-700">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-slate-800 dark:text-white">
                                Riwayat Perubahan Stok
                            </h3>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="light"
                                    onClick={() => setShowFilters(!showFilters)}
                                    className="flex items-center gap-2"
                                >
                                    {showFilters ? (
                                        <IconFilterOff className="w-4 h-4" />
                                    ) : (
                                        <IconFilter className="w-4 h-4" />
                                    )}
                                    Filter
                                    {hasActiveFilters && (
                                        <span className="w-2 h-2 rounded-full bg-blue-500"></span>
                                    )}
                                </Button>
                            </div>
                        </div>

                        {/* Expanded Filters */}
                        {showFilters && (
                            <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Tipe
                                    </label>
                                    <select
                                        name="type"
                                        value={filterData.type}
                                        onChange={handleFilterChange}
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white"
                                    >
                                        <option value="">Semua Tipe</option>
                                        {Object.entries(types).map(([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Dari Tanggal
                                    </label>
                                    <input
                                        type="date"
                                        name="from"
                                        value={filterData.from}
                                        onChange={handleFilterChange}
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Sampai Tanggal
                                    </label>
                                    <input
                                        type="date"
                                        name="to"
                                        value={filterData.to}
                                        onChange={handleFilterChange}
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white"
                                    />
                                </div>
                                <div className="flex items-end gap-2">
                                    <Button variant="primary" onClick={applyFilters}>
                                        Terapkan
                                    </Button>
                                    <Button variant="light" onClick={resetFilters}>
                                        Reset
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className="w-12">#</Table.Th>
                                    <Table.Th className="w-28">Tipe</Table.Th>
                                    <Table.Th className="w-28 text-right">
                                        Sebelum
                                    </Table.Th>
                                    <Table.Th className="w-28 text-right">
                                        Perubahan
                                    </Table.Th>
                                    <Table.Th className="w-28 text-right">
                                        Sesudah
                                    </Table.Th>
                                    <Table.Th>Alasan</Table.Th>
                                    <Table.Th>Pengguna</Table.Th>
                                    <Table.Th className="w-40">Waktu</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {adjustments?.data?.length === 0 ? (
                                    <tr>
                                        <Table.Td colSpan={8} className="text-center py-12">
                                            <div className="flex flex-col items-center gap-2 text-slate-500 dark:text-slate-400">
                                                <IconDatabaseOff className="w-12 h-12" />
                                                <p>Tidak ada riwayat perubahan stok</p>
                                            </div>
                                        </Table.Td>
                                    </tr>
                                ) : (
                                    adjustments?.data?.map((adjustment, index) => (
                                        <tr key={adjustment.id}>
                                            <Table.Td className="text-center text-slate-500">
                                                {adjustments.from + index}
                                            </Table.Td>
                                            <Table.Td>
                                                <Link
                                                    href={route(
                                                        "inventory-adjustments.show",
                                                        adjustment.id
                                                    )}
                                                >
                                                    {getTypeBadge(adjustment.type)}
                                                </Link>
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {Number(
                                                    adjustment.quantity_before
                                                ).toLocaleString("id-ID")}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                <span
                                                    className={
                                                        adjustment.quantity_change > 0
                                                            ? "text-green-600 dark:text-green-400"
                                                            : "text-red-600 dark:text-red-400"
                                                    }
                                                >
                                                    {adjustment.quantity_change > 0
                                                        ? "+"
                                                        : ""}
                                                    {Number(
                                                        adjustment.quantity_change
                                                    ).toLocaleString("id-ID")}
                                                </span>
                                            </Table.Td>
                                            <Table.Td className="text-right font-medium">
                                                {Number(
                                                    adjustment.quantity_after
                                                ).toLocaleString("id-ID")}
                                            </Table.Td>
                                            <Table.Td>
                                                <p className="text-sm text-slate-600 dark:text-slate-300 truncate max-w-[200px]">
                                                    {adjustment.reason || "-"}
                                                </p>
                                            </Table.Td>
                                            <Table.Td className="text-sm">
                                                {adjustment.user?.name || "System"}
                                            </Table.Td>
                                            <Table.Td className="text-sm text-slate-500">
                                                {formatDateTime(adjustment.created_at)}
                                            </Table.Td>
                                        </tr>
                                    ))
                                )}
                            </Table.Tbody>
                        </Table>
                    </div>
                </div>

                {/* Pagination */}
                {adjustments?.data?.length > 0 && (
                    <Pagination links={adjustments.links} />
                )}
            </div>
        </DashboardLayout>
    );
}
