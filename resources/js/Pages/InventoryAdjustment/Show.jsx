import React from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconArrowLeft,
    IconPackage,
    IconUser,
    IconCalendar,
    IconReceipt,
    IconArrowUp,
    IconArrowDown,
    IconExternalLink,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import { formatDateTime, formatDateTimeFull } from "@/Utils/DateHelper";

export default function Show({ adjustment, reference, relatedAdjustments, types }) {

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
    const getTypeBadge = (type, large = false) => {
        const inTypes = ["in", "purchase", "return"];
        const isIncoming = inTypes.includes(type);

        return (
            <span
                className={`inline-flex items-center gap-1 ${
                    large ? "px-3 py-1 text-sm" : "px-2 py-0.5 text-xs"
                } rounded-full font-medium ${
                    isIncoming
                        ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        : "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                }`}
            >
                {isIncoming ? (
                    <IconArrowUp className={large ? "w-4 h-4" : "w-3 h-3"} />
                ) : (
                    <IconArrowDown className={large ? "w-4 h-4" : "w-3 h-3"} />
                )}
                {types[type] || type}
            </span>
        );
    };

    return (
        <DashboardLayout>
            <Head title={`Adjustment - ${adjustment.id}`} />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href={route("inventory-adjustments.index")}>
                        <Button variant="light" className="p-2" title="Kembali">
                            <IconArrowLeft className="w-5 h-5" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-800 dark:text-white">
                            Detail Adjustment
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            ID: {adjustment.id}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Adjustment Info Card */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                    <IconPackage className="w-5 h-5 text-blue-500" />
                                    Informasi Adjustment
                                </h2>
                                {getTypeBadge(adjustment.type, true)}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Left Column */}
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            Produk
                                        </p>
                                        <Link
                                            href={route(
                                                "inventory-adjustments.product-history",
                                                adjustment.product_id
                                            )}
                                            className="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                        >
                                            {adjustment.product?.title || "-"}
                                        </Link>
                                        <p className="text-xs text-slate-500">
                                            {adjustment.product?.barcode}
                                            {adjustment.product?.category?.name &&
                                                ` • ${adjustment.product.category.name}`}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            Alasan
                                        </p>
                                        <p className="font-medium text-slate-800 dark:text-white">
                                            {adjustment.reason || "-"}
                                        </p>
                                    </div>
                                    {adjustment.notes && (
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Catatan
                                            </p>
                                            <p className="text-slate-800 dark:text-white">
                                                {adjustment.notes}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Right Column */}
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            Dibuat oleh
                                        </p>
                                        <p className="font-medium text-slate-800 dark:text-white flex items-center gap-1">
                                            <IconUser className="w-4 h-4" />
                                            {adjustment.user?.name || "System"}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            Waktu
                                        </p>
                                        <p className="font-medium text-slate-800 dark:text-white flex items-center gap-1">
                                            <IconCalendar className="w-4 h-4" />
                                            {formatDateTime(adjustment.created_at)}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Stock Change Visualization */}
                            <div className="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                                <p className="text-sm text-slate-500 dark:text-slate-400 mb-3">
                                    Perubahan Stok
                                </p>
                                <div className="flex items-center justify-center gap-4 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                    <div className="text-center">
                                        <p className="text-sm text-slate-500">
                                            Sebelum
                                        </p>
                                        <p className="text-2xl font-bold text-slate-800 dark:text-white">
                                            {Number(
                                                adjustment.quantity_before
                                            ).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                    <div
                                        className={`flex items-center justify-center w-12 h-12 rounded-full ${
                                            adjustment.quantity_change > 0
                                                ? "bg-green-100 dark:bg-green-900/30"
                                                : "bg-red-100 dark:bg-red-900/30"
                                        }`}
                                    >
                                        {adjustment.quantity_change > 0 ? (
                                            <IconArrowUp className="w-6 h-6 text-green-600 dark:text-green-400" />
                                        ) : (
                                            <IconArrowDown className="w-6 h-6 text-red-600 dark:text-red-400" />
                                        )}
                                    </div>
                                    <div className="text-center">
                                        <p className="text-sm text-slate-500">
                                            Perubahan
                                        </p>
                                        <p
                                            className={`text-2xl font-bold ${
                                                adjustment.quantity_change > 0
                                                    ? "text-green-600 dark:text-green-400"
                                                    : "text-red-600 dark:text-red-400"
                                            }`}
                                        >
                                            {adjustment.quantity_change > 0
                                                ? "+"
                                                : ""}
                                            {Number(
                                                adjustment.quantity_change
                                            ).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                    <div className="text-3xl text-slate-300 dark:text-slate-600">
                                        →
                                    </div>
                                    <div className="text-center">
                                        <p className="text-sm text-slate-500">
                                            Sesudah
                                        </p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                            {Number(
                                                adjustment.quantity_after
                                            ).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Reference Info */}
                        {reference && (
                            <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                                <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                    <IconReceipt className="w-5 h-5 text-blue-500" />
                                    Referensi:{" "}
                                    {adjustment.reference_type === "purchase"
                                        ? "Pembelian"
                                        : "Transaksi"}
                                </h2>

                                {adjustment.reference_type === "purchase" && (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Reference
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {reference.reference || "-"}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Supplier
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {reference.supplier_name || "-"}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Tanggal
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {formatDateTime(
                                                    reference.purchase_date
                                                )}
                                            </span>
                                        </div>
                                        <div className="pt-3 border-t border-slate-200 dark:border-slate-700">
                                            <Link
                                                href={route(
                                                    "purchase.show",
                                                    reference.id
                                                )}
                                            >
                                                <Button
                                                    variant="light"
                                                    className="flex items-center gap-2"
                                                >
                                                    <IconExternalLink className="w-4 h-4" />
                                                    Lihat Detail Pembelian
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                )}

                                {adjustment.reference_type === "transaction" && (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Invoice
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {reference.invoice || "-"}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Customer
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {reference.customer?.name ||
                                                    "Walk-in Customer"}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-slate-500 dark:text-slate-400">
                                                Total
                                            </span>
                                            <span className="font-medium text-slate-800 dark:text-white">
                                                {formatCurrency(
                                                    reference.grand_total
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Related Adjustments */}
                        {relatedAdjustments?.length > 0 && (
                            <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                                <div className="p-4 border-b border-slate-200 dark:border-slate-700">
                                    <h2 className="text-lg font-semibold text-slate-800 dark:text-white">
                                        Riwayat Adjustment Produk Ini
                                    </h2>
                                </div>
                                <div className="overflow-x-auto">
                                    <Table>
                                        <Table.Thead>
                                            <tr>
                                                <Table.Th className="w-28">
                                                    Tipe
                                                </Table.Th>
                                                <Table.Th className="w-24 text-right">
                                                    Sebelum
                                                </Table.Th>
                                                <Table.Th className="w-24 text-right">
                                                    Perubahan
                                                </Table.Th>
                                                <Table.Th className="w-24 text-right">
                                                    Sesudah
                                                </Table.Th>
                                                <Table.Th>Alasan</Table.Th>
                                                <Table.Th className="w-40">
                                                    Waktu
                                                </Table.Th>
                                            </tr>
                                        </Table.Thead>
                                        <Table.Tbody>
                                            {relatedAdjustments.map((adj) => (
                                                <tr key={adj.id}>
                                                    <Table.Td>
                                                        <Link
                                                            href={route(
                                                                "inventory-adjustments.show",
                                                                adj.id
                                                            )}
                                                        >
                                                            {getTypeBadge(adj.type)}
                                                        </Link>
                                                    </Table.Td>
                                                    <Table.Td className="text-right">
                                                        {Number(
                                                            adj.quantity_before
                                                        ).toLocaleString("id-ID")}
                                                    </Table.Td>
                                                    <Table.Td className="text-right">
                                                        <span
                                                            className={
                                                                adj.quantity_change >
                                                                0
                                                                    ? "text-green-600 dark:text-green-400"
                                                                    : "text-red-600 dark:text-red-400"
                                                            }
                                                        >
                                                            {adj.quantity_change > 0
                                                                ? "+"
                                                                : ""}
                                                            {Number(
                                                                adj.quantity_change
                                                            ).toLocaleString(
                                                                "id-ID"
                                                            )}
                                                        </span>
                                                    </Table.Td>
                                                    <Table.Td className="text-right font-medium">
                                                        {Number(
                                                            adj.quantity_after
                                                        ).toLocaleString("id-ID")}
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <p className="text-sm text-slate-600 dark:text-slate-300 truncate max-w-[150px]">
                                                            {adj.reason || "-"}
                                                        </p>
                                                    </Table.Td>
                                                    <Table.Td className="text-sm text-slate-500">
                                                        {formatDateTime(
                                                            adj.created_at
                                                        )}
                                                    </Table.Td>
                                                </tr>
                                            ))}
                                        </Table.Tbody>
                                    </Table>
                                </div>
                                <div className="p-4 border-t border-slate-200 dark:border-slate-700">
                                    <Link
                                        href={route(
                                            "inventory-adjustments.product-history",
                                            adjustment.product_id
                                        )}
                                    >
                                        <Button variant="light" className="w-full">
                                            Lihat Semua Riwayat
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Product Card */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4">
                                Info Produk
                            </h2>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Nama
                                    </p>
                                    <p className="font-medium text-slate-800 dark:text-white">
                                        {adjustment.product?.title || "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Barcode
                                    </p>
                                    <p className="font-medium text-slate-800 dark:text-white">
                                        {adjustment.product?.barcode || "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Kategori
                                    </p>
                                    <p className="font-medium text-slate-800 dark:text-white">
                                        {adjustment.product?.category?.name || "-"}
                                    </p>
                                </div>
                                <hr className="border-slate-200 dark:border-slate-700" />
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Stok Saat Ini
                                    </p>
                                    <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        {adjustment.product?.inventory?.quantity?.toLocaleString(
                                            "id-ID"
                                        ) || "-"}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4">
                                Aksi Cepat
                            </h2>
                            <div className="space-y-2">
                                <Link
                                    href={route(
                                        "inventory-adjustments.product-history",
                                        adjustment.product_id
                                    )}
                                    className="block"
                                >
                                    <Button variant="light" className="w-full">
                                        Riwayat Produk
                                    </Button>
                                </Link>
                                <Link
                                    href={route("inventory-adjustments.create")}
                                    className="block"
                                >
                                    <Button variant="primary" className="w-full">
                                        Buat Adjustment Baru
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
