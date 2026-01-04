import React from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconArrowLeft,
    IconPencil,
    IconPrinter,
    IconPackage,
    IconUser,
    IconCalendar,
    IconReceipt,
    IconNotes,
    IconTruck,
    IconArrowUp,
    IconArrowDown,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import { formatDateTime, formatDate } from "@/Utils/DateHelper";

export default function Show({ purchase, inventoryAdjustments, totals }) {
    // Format currency
    const formatCurrency = (value) => {
        const num = Number(value);
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        }).format(isNaN(num) ? 0 : num);
    };

    // Status badge
    const getStatusBadge = (status) => {
        const styles = {
            pending:
                "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400",
            paid: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
            received:
                "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        };
        return (
            <span
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    styles[status] || styles.pending
                }`}
            >
                {status?.charAt(0).toUpperCase() + status?.slice(1)}
            </span>
        );
    };

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
                {type?.charAt(0).toUpperCase() + type?.slice(1)}
            </span>
        );
    };

    return (
        <DashboardLayout>
            <Head title={`Purchase - ${purchase.reference || "Detail"}`} />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route("purchase.index")}>
                            <Button
                                variant="light"
                                className="p-2"
                                title="Kembali"
                            >
                                <IconArrowLeft className="w-5 h-5" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-800 dark:text-white">
                                Detail Pembelian
                            </h1>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {purchase.reference || "No Reference"}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="light" className="flex items-center gap-2">
                            <IconPrinter className="w-4 h-4" />
                            <span className="hidden sm:inline">Print</span>
                        </Button>
                        <Link href={route("purchase.edit", purchase.id)}>
                            <Button
                                variant="primary"
                                className="flex items-center gap-2"
                            >
                                <IconPencil className="w-4 h-4" />
                                <span className="hidden sm:inline">Edit</span>
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Purchase Info Card */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                <IconReceipt className="w-5 h-5 text-blue-500" />
                                Informasi Pembelian
                            </h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <IconTruck className="w-5 h-5 text-slate-400 mt-0.5" />
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Supplier
                                            </p>
                                            <p className="font-medium text-slate-800 dark:text-white">
                                                {purchase.supplier_name || "-"}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <IconCalendar className="w-5 h-5 text-slate-400 mt-0.5" />
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Tanggal Pembelian
                                            </p>
                                            <p className="font-medium text-slate-800 dark:text-white">
                                                {formatDate(
                                                    purchase.purchase_date
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <IconReceipt className="w-5 h-5 text-slate-400 mt-0.5" />
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Reference
                                            </p>
                                            <p className="font-medium text-slate-800 dark:text-white">
                                                {purchase.reference || "-"}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div className="w-5 h-5 flex items-center justify-center">
                                            <span className="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                                        </div>
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Status
                                            </p>
                                            <div className="mt-0.5">
                                                {getStatusBadge(purchase.status)}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {purchase.notes && (
                                <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                                    <div className="flex items-start gap-3">
                                        <IconNotes className="w-5 h-5 text-slate-400 mt-0.5" />
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Catatan
                                            </p>
                                            <p className="text-slate-800 dark:text-white mt-1">
                                                {purchase.notes}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Items Table */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                            <div className="p-4 border-b border-slate-200 dark:border-slate-700">
                                <h2 className="text-lg font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                    <IconPackage className="w-5 h-5 text-blue-500" />
                                    Item Pembelian ({totals.total_items} item)
                                </h2>
                            </div>
                            <div className="overflow-x-auto">
                                <Table>
                                    <Table.Thead>
                                        <tr>
                                            <Table.Th className="w-12">#</Table.Th>
                                            <Table.Th>Produk</Table.Th>
                                            <Table.Th className="w-24 text-center">
                                                Qty
                                            </Table.Th>
                                            <Table.Th className="w-32 text-right">
                                                Harga
                                            </Table.Th>
                                            <Table.Th className="w-20 text-center">
                                                Tax %
                                            </Table.Th>
                                            <Table.Th className="w-20 text-center">
                                                Disc %
                                            </Table.Th>
                                            <Table.Th className="w-36 text-right">
                                                Total
                                            </Table.Th>
                                        </tr>
                                    </Table.Thead>
                                    <Table.Tbody>
                                        {purchase.items?.map((item, index) => (
                                            <tr key={item.id}>
                                                <Table.Td className="text-center text-slate-500">
                                                    {index + 1}
                                                </Table.Td>
                                                <Table.Td>
                                                    <div>
                                                        <p className="font-medium text-slate-800 dark:text-white">
                                                            {item.product?.name ||
                                                                item.description ||
                                                                "-"}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            {item.barcode}
                                                            {item.product
                                                                ?.category
                                                                ?.name &&
                                                                ` • ${item.product.category.name}`}
                                                        </p>
                                                    </div>
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    {Number(
                                                        item.quantity
                                                    ).toLocaleString("id-ID")}
                                                </Table.Td>
                                                <Table.Td className="text-right">
                                                    {formatCurrency(
                                                        item.purchase_price
                                                    )}
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    {item.tax_percent || 0}%
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    {item.discount_percent || 0}%
                                                </Table.Td>
                                                <Table.Td className="text-right font-medium">
                                                    {formatCurrency(
                                                        item.total_price
                                                    )}
                                                </Table.Td>
                                            </tr>
                                        ))}
                                    </Table.Tbody>
                                    <Table.Tfoot>
                                        <tr>
                                            <Table.Td
                                                colSpan={2}
                                                className="text-right font-medium text-slate-600 dark:text-slate-300"
                                            >
                                                Total Qty:
                                            </Table.Td>
                                            <Table.Td className="text-center font-bold text-slate-800 dark:text-white">
                                                {Number(
                                                    totals.total_quantity
                                                ).toLocaleString("id-ID")}
                                            </Table.Td>
                                            <Table.Td
                                                colSpan={3}
                                                className="text-right font-medium text-slate-600 dark:text-slate-300"
                                            >
                                                Subtotal:
                                            </Table.Td>
                                            <Table.Td className="text-right font-bold text-slate-800 dark:text-white">
                                                {formatCurrency(totals.subtotal)}
                                            </Table.Td>
                                        </tr>
                                        {totals.total_tax > 0 && (
                                            <tr>
                                                <Table.Td
                                                    colSpan={6}
                                                    className="text-right font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    Total Tax:
                                                </Table.Td>
                                                <Table.Td className="text-right font-medium text-slate-800 dark:text-white">
                                                    {formatCurrency(
                                                        totals.total_tax
                                                    )}
                                                </Table.Td>
                                            </tr>
                                        )}
                                        {totals.total_discount > 0 && (
                                            <tr>
                                                <Table.Td
                                                    colSpan={6}
                                                    className="text-right font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    Total Discount:
                                                </Table.Td>
                                                <Table.Td className="text-right font-medium text-green-600 dark:text-green-400">
                                                    -
                                                    {formatCurrency(
                                                        totals.total_discount
                                                    )}
                                                </Table.Td>
                                            </tr>
                                        )}
                                        <tr className="bg-slate-50 dark:bg-slate-900/50">
                                            <Table.Td
                                                colSpan={6}
                                                className="text-right text-lg font-bold text-slate-800 dark:text-white"
                                            >
                                                Grand Total:
                                            </Table.Td>
                                            <Table.Td className="text-right text-lg font-bold text-blue-600 dark:text-blue-400">
                                                {formatCurrency(
                                                    totals.subtotal +
                                                        totals.total_tax -
                                                        totals.total_discount
                                                )}
                                            </Table.Td>
                                        </tr>
                                    </Table.Tfoot>
                                </Table>
                            </div>
                        </div>

                        {/* Inventory Adjustments */}
                        {inventoryAdjustments?.length > 0 && (
                            <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                                <div className="p-4 border-b border-slate-200 dark:border-slate-700">
                                    <h2 className="text-lg font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                        <IconPackage className="w-5 h-5 text-green-500" />
                                        Perubahan Inventori
                                    </h2>
                                </div>
                                <div className="overflow-x-auto">
                                    <Table>
                                        <Table.Thead>
                                            <tr>
                                                <Table.Th>Produk</Table.Th>
                                                <Table.Th className="w-24">
                                                    Tipe
                                                </Table.Th>
                                                <Table.Th className="w-28 text-right">
                                                    Sebelum
                                                </Table.Th>
                                                <Table.Th className="w-28 text-right">
                                                    Perubahan
                                                </Table.Th>
                                                <Table.Th className="w-28 text-right">
                                                    Sesudah
                                                </Table.Th>
                                                <Table.Th className="w-40">
                                                    Waktu
                                                </Table.Th>
                                            </tr>
                                        </Table.Thead>
                                        <Table.Tbody>
                                            {inventoryAdjustments.map(
                                                (adjustment) => (
                                                    <tr key={adjustment.id}>
                                                        <Table.Td>
                                                            <div>
                                                                <p className="font-medium text-slate-800 dark:text-white">
                                                                    {adjustment
                                                                        .product
                                                                        ?.name ||
                                                                        "-"}
                                                                </p>
                                                                <p className="text-xs text-slate-500">
                                                                    {
                                                                        adjustment
                                                                            .product
                                                                            ?.barcode
                                                                    }
                                                                </p>
                                                            </div>
                                                        </Table.Td>
                                                        <Table.Td>
                                                            {getTypeBadge(
                                                                adjustment.type
                                                            )}
                                                        </Table.Td>
                                                        <Table.Td className="text-right">
                                                            {Number(
                                                                adjustment.quantity_before
                                                            ).toLocaleString(
                                                                "id-ID"
                                                            )}
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
                                                                {adjustment.quantity_change >
                                                                0
                                                                    ? "+"
                                                                    : ""}
                                                                {Number(
                                                                    adjustment.quantity_change
                                                                ).toLocaleString(
                                                                    "id-ID"
                                                                )}
                                                            </span>
                                                        </Table.Td>
                                                        <Table.Td className="text-right font-medium">
                                                            {Number(
                                                                adjustment.quantity_after
                                                            ).toLocaleString(
                                                                "id-ID"
                                                            )}
                                                        </Table.Td>
                                                        <Table.Td className="text-sm text-slate-500">
                                                            {formatDateTime(
                                                                adjustment.created_at
                                                            )}
                                                        </Table.Td>
                                                    </tr>
                                                )
                                            )}
                                        </Table.Tbody>
                                    </Table>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Summary Card */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4">
                                Ringkasan
                            </h2>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Total Item
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {totals.total_items}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Total Quantity
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {Number(
                                            totals.total_quantity
                                        ).toLocaleString("id-ID")}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Subtotal
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {formatCurrency(totals.subtotal)}
                                    </span>
                                </div>
                                {totals.total_tax > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">
                                            Tax
                                        </span>
                                        <span className="font-medium text-slate-800 dark:text-white">
                                            {formatCurrency(totals.total_tax)}
                                        </span>
                                    </div>
                                )}
                                {totals.total_discount > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">
                                            Discount
                                        </span>
                                        <span className="font-medium text-green-600 dark:text-green-400">
                                            -{formatCurrency(totals.total_discount)}
                                        </span>
                                    </div>
                                )}
                                <hr className="border-slate-200 dark:border-slate-700" />
                                <div className="flex justify-between text-lg">
                                    <span className="font-semibold text-slate-800 dark:text-white">
                                        Grand Total
                                    </span>
                                    <span className="font-bold text-blue-600 dark:text-blue-400">
                                        {formatCurrency(
                                            totals.subtotal +
                                                totals.total_tax -
                                                totals.total_discount
                                        )}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Meta Info */}
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4">
                                Info Tambahan
                            </h2>
                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Tax Included
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {purchase.tax_included ? "Ya" : "Tidak"}
                                    </span>
                                </div>
                                {purchase.user && (
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">
                                            Dibuat oleh
                                        </span>
                                        <span className="font-medium text-slate-800 dark:text-white">
                                            {purchase.user.name}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Dibuat pada
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {formatDateTime(purchase.created_at)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500 dark:text-slate-400">
                                        Diupdate pada
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-white">
                                        {formatDateTime(purchase.updated_at)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
