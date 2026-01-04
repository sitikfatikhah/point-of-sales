import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link, router } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconSearch,
    IconFilter,
    IconFilterOff,
    IconPlus,
    IconEye,
    IconArrowUp,
    IconArrowDown,
    IconPackage,
    IconDatabaseOff,
    IconRefresh,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import Pagination from "@/Components/Dashboard/Pagination";
import { formatDateTime } from "@/Utils/DateHelper";

export default function Index({
    adjustments,
    products,
    summary,
    types,
    filters,
}) {
    const [showFilters, setShowFilters] = useState(false);
    const [filterData, setFilterData] = useState({
        search: filters?.search || "",
        product_id: filters?.product_id || "",
        type: filters?.type || "",
        from: filters?.from || "",
        to: filters?.to || "",
    });

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilterData((prev) => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get(route("inventory-adjustments.index"), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const emptyFilters = {
            search: "",
            product_id: "",
            type: "",
            from: "",
            to: "",
        };
        setFilterData(emptyFilters);
        router.get(
            route("inventory-adjustments.index"),
            {},
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleKeyDown = (e) => {
        if (e.key === "Enter") {
            applyFilters();
        }
    };

    const hasActiveFilters = Object.values(filterData).some((v) => v !== "");

    // Get type badge for inventory adjustment
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

    const handleSync = () => {
        router.post(
            route("inventory-adjustments.sync"),
            {},
            {
                onSuccess: () => {
                    // Success message handled by controller
                },
            }
        );
    };

    return (
        <DashboardLayout>
            <Head title="Inventory Adjustments" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-800 dark:text-white">
                            Inventory Adjustments
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Riwayat perubahan stok produk
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="light"
                            onClick={handleSync}
                            className="flex items-center gap-2"
                        >
                            <IconRefresh className="w-4 h-4" />
                            <span className="hidden sm:inline">Sync</span>
                        </Button>
                        <Link href={route("inventory-adjustments.create")}>
                            <Button
                                variant="primary"
                                className="flex items-center gap-2"
                            >
                                <IconPlus className="w-4 h-4" />
                                <span>Adjustment</span>
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Total Produk
                        </p>
                        <p className="text-2xl font-bold text-slate-800 dark:text-white">
                            {summary.total_products?.toLocaleString("id-ID")}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Nilai Stok (Modal)
                        </p>
                        <p className="text-xl font-bold text-blue-600 dark:text-blue-400">
                            {new Intl.NumberFormat("id-ID", {
                                style: "currency",
                                currency: "IDR",
                                minimumFractionDigits: 0,
                                notation: "compact",
                            }).format(summary.total_stock_value || 0)}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Nilai Stok (Jual)
                        </p>
                        <p className="text-xl font-bold text-green-600 dark:text-green-400">
                            {new Intl.NumberFormat("id-ID", {
                                style: "currency",
                                currency: "IDR",
                                minimumFractionDigits: 0,
                                notation: "compact",
                            }).format(summary.total_sell_value || 0)}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Stok Rendah
                        </p>
                        <p className="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                            {summary.low_stock_count?.toLocaleString("id-ID")}
                        </p>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Habis Stok
                        </p>
                        <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                            {summary.out_of_stock_count?.toLocaleString("id-ID")}
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                    <div className="p-4 border-b border-slate-200 dark:border-slate-700">
                        <div className="flex flex-col md:flex-row gap-4">
                            {/* Search */}
                            <div className="flex-1 relative">
                                <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                                <input
                                    type="text"
                                    name="search"
                                    placeholder="Cari produk, alasan..."
                                    value={filterData.search}
                                    onChange={handleFilterChange}
                                    onKeyDown={handleKeyDown}
                                    className="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
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
                                <Button variant="primary" onClick={applyFilters}>
                                    Cari
                                </Button>
                            </div>
                        </div>

                        {/* Expanded Filters */}
                        {showFilters && (
                            <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Produk
                                    </label>
                                    <select
                                        name="product_id"
                                        value={filterData.product_id}
                                        onChange={handleFilterChange}
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white"
                                    >
                                        <option value="">Semua Produk</option>
                                        {products?.map((product) => (
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.title} ({product.barcode})
                                            </option>
                                        ))}
                                    </select>
                                </div>
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
                                        {Object.entries(types).map(
                                            ([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            )
                                        )}
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
                                <div className="md:col-span-4 flex justify-end">
                                    <Button variant="light" onClick={resetFilters}>
                                        Reset Filter
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
                                    <Table.Th>Produk</Table.Th>
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
                                    <Table.Th className="w-40">Waktu</Table.Th>
                                    <Table.Th className="w-16">Aksi</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {adjustments?.data?.length === 0 ? (
                                    <tr>
                                        <Table.Td
                                            colSpan={9}
                                            className="text-center py-12"
                                        >
                                            <div className="flex flex-col items-center gap-2 text-slate-500 dark:text-slate-400">
                                                <IconDatabaseOff className="w-12 h-12" />
                                                <p>
                                                    Tidak ada data adjustment
                                                    ditemukan
                                                </p>
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
                                                <div>
                                                    <p className="font-medium text-slate-800 dark:text-white">
                                                        {adjustment.product?.title ||
                                                            "-"}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {adjustment.product?.barcode}
                                                    </p>
                                                </div>
                                            </Table.Td>
                                            <Table.Td>
                                                {getTypeBadge(adjustment.type)}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {Number(
                                                    adjustment.quantity_before
                                                ).toLocaleString("id-ID")}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                <span
                                                    className={
                                                        adjustment.quantity_change >
                                                        0
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
                                            <Table.Td className="text-sm text-slate-500">
                                                {formatDateTime(
                                                    adjustment.created_at
                                                )}
                                            </Table.Td>
                                            <Table.Td>
                                                <Link
                                                    href={route(
                                                        "inventory-adjustments.show",
                                                        adjustment.id
                                                    )}
                                                >
                                                    <Button
                                                        variant="light"
                                                        className="p-2"
                                                    >
                                                        <IconEye className="w-4 h-4" />
                                                    </Button>
                                                </Link>
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
