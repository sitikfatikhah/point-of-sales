import React, { useState, Fragment } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import {
    IconDatabaseOff,
    IconSearch,
    IconEye,
    IconX,
    IconPackage,
    IconArrowUp,
    IconArrowDown,
    IconAlertTriangle,
    IconCheck,
    IconAdjustments,
    IconHistory,
    IconRefresh,
    IconFilter,
    IconFilterOff,
} from '@tabler/icons-react';
import Table from '@/Components/Dashboard/Table';
import Pagination from '@/Components/Dashboard/Pagination';
import Button from '@/Components/Dashboard/Button';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import { formatDateTime } from '@/Utils/DateHelper';

export default function Inventories({ products, summary, filters, categories, recentAdjustments }) {
    const [filterData, setFilterData] = useState({
        search: filters?.search || '',
        category_id: filters?.category_id || '',
        stock_status: filters?.stock_status || '',
    });
    const [showFilters, setShowFilters] = useState(false);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [productDetail, setProductDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilterData(prev => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get(route('reports.inventories.index'), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const emptyFilters = { search: '', category_id: '', stock_status: '' };
        setFilterData(emptyFilters);
        router.get(route('reports.inventories.index'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            applyFilters();
        }
    };

    const hasActiveFilters = Object.values(filterData).some(v => v !== '');

    const handleShowDetail = async (product) => {
        setSelectedProduct(product);
        setShowDetailModal(true);
        setLoadingDetail(true);

        try {
            const response = await axios.get(`/dashboard/reports/inventories/${product.id}`);
            setProductDetail(response.data);
        } catch (error) {
            console.error('Error fetching product detail:', error);
        } finally {
            setLoadingDetail(false);
        }
    };

    const closeDetailModal = () => {
        setShowDetailModal(false);
        setSelectedProduct(null);
        setProductDetail(null);
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(amount || 0);
    };

    const formatNumber = (num) => {
        return new Intl.NumberFormat('id-ID').format(num || 0);
    };

    const getStockBadge = (stock) => {
        if (stock <= 0) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    <IconAlertTriangle className="w-3 h-3" />
                    Habis
                </span>
            );
        }
        if (stock <= 10) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                    <IconAlertTriangle className="w-3 h-3" />
                    Rendah
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                <IconCheck className="w-3 h-3" />
                Tersedia
            </span>
        );
    };

    const getAdjustmentTypeBadge = (type) => {
        const inTypes = ['in', 'purchase', 'return'];
        const isIncoming = inTypes.includes(type);
        const typeLabels = {
            in: 'Masuk',
            out: 'Keluar',
            purchase: 'Pembelian',
            sale: 'Penjualan',
            return: 'Retur',
            damage: 'Kerusakan',
            correction: 'Koreksi',
            adjustment: 'Penyesuaian',
        };

        return (
            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                isIncoming
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : type === 'correction'
                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
            }`}>
                {isIncoming ? <IconArrowUp className="w-3 h-3" /> : <IconArrowDown className="w-3 h-3" />}
                {typeLabels[type] || type}
            </span>
        );
    };

    return (
        <>
            <Head title="Laporan Persediaan" />

            <div className="p-4 lg:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-800 dark:text-white">
                            Laporan Persediaan
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Monitor stok dan pergerakan barang
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route('inventory-adjustments.index')}>
                            <Button variant="outline" type="button" icon={<IconAdjustments className="w-4 h-4" />} label="Penyesuaian Stok" />
                        </Link>
                        <Button
                            variant={showFilters ? 'primary' : 'secondary'}
                            type="button"
                            onClick={() => setShowFilters(!showFilters)}
                            icon={showFilters ? <IconFilterOff className="w-4 h-4" /> : <IconFilter className="w-4 h-4" />}
                            label={showFilters ? 'Tutup' : 'Filter'}
                        />
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <IconPackage className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Total Produk</p>
                                <p className="text-xl font-bold text-slate-800 dark:text-white">{formatNumber(summary.total_products)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <IconArrowUp className="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Nilai Modal</p>
                                <p className="text-lg font-bold text-green-600 dark:text-green-400">{formatCurrency(summary.total_stock_value)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <IconAlertTriangle className="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                            </div>
                            <div>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Stok Rendah</p>
                                <p className="text-xl font-bold text-yellow-600 dark:text-yellow-400">{formatNumber(summary.low_stock_count)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                <IconDatabaseOff className="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Stok Habis</p>
                                <p className="text-xl font-bold text-red-600 dark:text-red-400">{formatNumber(summary.out_of_stock_count)}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                {showFilters && (
                    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="relative">
                                <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                                <input
                                    type="text"
                                    name="search"
                                    placeholder="Cari produk..."
                                    value={filterData.search}
                                    onChange={handleFilterChange}
                                    onKeyDown={handleKeyDown}
                                    className="w-full pl-9 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>
                            <select
                                name="category_id"
                                value={filterData.category_id}
                                onChange={handleFilterChange}
                                className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Semua Kategori</option>
                                {categories?.map(cat => (
                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                ))}
                            </select>
                            <select
                                name="stock_status"
                                value={filterData.stock_status}
                                onChange={handleFilterChange}
                                className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Semua Status</option>
                                <option value="available">Tersedia (&gt;10)</option>
                                <option value="low">Stok Rendah (1-10)</option>
                                <option value="out">Habis (0)</option>
                            </select>
                            <div className="flex gap-2">
                                <Button variant="primary" type="button" onClick={applyFilters} icon={<IconSearch className="w-4 h-4" />} label="Cari" />
                                {hasActiveFilters && (
                                    <Button variant="secondary" type="button" onClick={resetFilters} icon={<IconRefresh className="w-4 h-4" />} label="Reset" />
                                )}
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    {/* Main Table */}
                    <div className="xl:col-span-2">
                        <Table.Card title="Daftar Produk">
                            <Table>
                                <Table.Thead>
                                    <tr>
                                        <Table.Th className="w-12">#</Table.Th>
                                        <Table.Th>Produk</Table.Th>
                                        <Table.Th>Kategori</Table.Th>
                                        <Table.Th className="text-center">Stok</Table.Th>
                                        <Table.Th className="text-right">Nilai</Table.Th>
                                        <Table.Th className="text-center w-20">Aksi</Table.Th>
                                    </tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {products?.data?.length > 0 ? (
                                        products.data.map((product, index) => (
                                            <tr key={product.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                <Table.Td className="text-center text-slate-500">
                                                    {(products.current_page - 1) * products.per_page + index + 1}
                                                </Table.Td>
                                                <Table.Td>
                                                    <div>
                                                        <p className="font-medium text-slate-800 dark:text-white">{product.title}</p>
                                                        <p className="text-xs text-slate-500">{product.barcode || '-'}</p>
                                                    </div>
                                                </Table.Td>
                                                <Table.Td>
                                                    <span className="text-sm text-slate-600 dark:text-slate-400">
                                                        {product.category?.name || '-'}
                                                    </span>
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className="font-semibold text-slate-800 dark:text-white">
                                                            {formatNumber(product.stock)}
                                                        </span>
                                                        {getStockBadge(product.stock)}
                                                    </div>
                                                </Table.Td>
                                                <Table.Td className="text-right">
                                                    <div>
                                                        <p className="font-medium text-slate-800 dark:text-white">
                                                            {formatCurrency(product.stock * product.sell_price)}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            Modal: {formatCurrency(product.stock * product.buy_price)}
                                                        </p>
                                                    </div>
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    <button
                                                        onClick={() => handleShowDetail(product)}
                                                        className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 transition-colors"
                                                        title="Lihat Detail"
                                                    >
                                                        <IconEye className="w-4 h-4" />
                                                    </button>
                                                </Table.Td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <Table.Td colSpan={6} className="text-center py-12">
                                                <IconDatabaseOff size={48} className="mx-auto text-slate-300 dark:text-slate-600" />
                                                <p className="mt-2 text-slate-500 dark:text-slate-400">Tidak ada data produk</p>
                                            </Table.Td>
                                        </tr>
                                    )}
                                </Table.Tbody>
                            </Table>
                            {products?.data?.length > 0 && (
                                <div className="p-4 border-t border-slate-200 dark:border-slate-700">
                                    <Pagination links={products.links} />
                                </div>
                            )}
                        </Table.Card>
                    </div>

                    {/* Recent Adjustments Sidebar */}
                    <div className="xl:col-span-1">
                        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                            <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <h3 className="font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                                    <IconHistory className="w-4 h-4 text-slate-400" />
                                    Penyesuaian Terbaru
                                </h3>
                                <span className="text-xs text-slate-500 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded-full">
                                    Hari ini: {summary.today_adjustments}
                                </span>
                            </div>
                            <div className="divide-y divide-slate-200 dark:divide-slate-700">
                                {recentAdjustments?.length > 0 ? (
                                    recentAdjustments.map(adj => (
                                        <div key={adj.id} className="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex-1 min-w-0">
                                                    <p className="font-medium text-sm text-slate-800 dark:text-white truncate">
                                                        {adj.product?.title || '-'}
                                                    </p>
                                                    <p className="text-xs text-slate-500 truncate">
                                                        {adj.reason || 'Tidak ada alasan'}
                                                    </p>
                                                </div>
                                                <div className="text-right flex-shrink-0">
                                                    {getAdjustmentTypeBadge(adj.type)}
                                                    <p className={`text-sm font-semibold mt-1 ${
                                                        adj.quantity_change > 0 ? 'text-green-600' : 'text-red-600'
                                                    }`}>
                                                        {adj.quantity_change > 0 ? '+' : ''}{formatNumber(adj.quantity_change)}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-1 flex items-center justify-between text-xs text-slate-400">
                                                <span>{adj.user?.name || 'System'}</span>
                                                <span>{formatDateTime(adj.created_at)}</span>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-6 text-center text-slate-500">
                                        <IconHistory className="w-8 h-8 mx-auto mb-2 opacity-50" />
                                        <p className="text-sm">Belum ada penyesuaian</p>
                                    </div>
                                )}
                            </div>
                            {recentAdjustments?.length > 0 && (
                                <div className="p-3 border-t border-slate-200 dark:border-slate-700">
                                    <Link
                                        href={route('inventory-adjustments.index')}
                                        className="block w-full text-center text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        Lihat Semua Penyesuaian →
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Detail Modal */}
            <Transition appear show={showDetailModal} as={Fragment}>
                <Dialog as="div" className="relative z-50" onClose={closeDetailModal}>
                    <Transition.Child
                        as={Fragment}
                        enter="ease-out duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-black/50" />
                    </Transition.Child>

                    <div className="fixed inset-0 overflow-y-auto">
                        <div className="flex min-h-full items-center justify-center p-4">
                            <Transition.Child
                                as={Fragment}
                                enter="ease-out duration-300"
                                enterFrom="opacity-0 scale-95"
                                enterTo="opacity-100 scale-100"
                                leave="ease-in duration-200"
                                leaveFrom="opacity-100 scale-100"
                                leaveTo="opacity-0 scale-95"
                            >
                                <Dialog.Panel className="w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 shadow-xl transition-all">
                                    {/* Modal Header */}
                                    <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                                        <Dialog.Title className="text-lg font-semibold text-slate-800 dark:text-white">
                                            Detail Produk
                                        </Dialog.Title>
                                        <button
                                            onClick={closeDetailModal}
                                            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500"
                                        >
                                            <IconX className="w-5 h-5" />
                                        </button>
                                    </div>

                                    {/* Modal Content */}
                                    <div className="p-6 max-h-[70vh] overflow-y-auto">
                                        {loadingDetail ? (
                                            <div className="flex items-center justify-center py-12">
                                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                            </div>
                                        ) : productDetail ? (
                                            <div className="space-y-6">
                                                {/* Product Info */}
                                                <div className="flex items-start gap-4">
                                                    <div className="w-16 h-16 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                                        <IconPackage className="w-8 h-8 text-slate-400" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <h3 className="text-xl font-semibold text-slate-800 dark:text-white">
                                                            {productDetail.product?.title}
                                                        </h3>
                                                        <p className="text-sm text-slate-500">
                                                            Barcode: {productDetail.product?.barcode || '-'} • Kategori: {productDetail.product?.category?.name || '-'}
                                                        </p>
                                                        <div className="mt-2">{getStockBadge(productDetail.product?.stock)}</div>
                                                    </div>
                                                </div>

                                                {/* Stats Grid */}
                                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3">
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">Stok Saat Ini</p>
                                                        <p className="text-xl font-bold text-blue-600 dark:text-blue-400">
                                                            {formatNumber(productDetail.product?.stock)}
                                                        </p>
                                                    </div>
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3">
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">Total Dibeli</p>
                                                        <p className="text-xl font-bold text-green-600 dark:text-green-400">
                                                            {formatNumber(productDetail.product?.total_purchased)}
                                                        </p>
                                                    </div>
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3">
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">Total Terjual</p>
                                                        <p className="text-xl font-bold text-red-600 dark:text-red-400">
                                                            {formatNumber(productDetail.product?.total_sold)}
                                                        </p>
                                                    </div>
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3">
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">Nilai Stok</p>
                                                        <p className="text-lg font-bold text-slate-800 dark:text-white">
                                                            {formatCurrency(productDetail.product?.stock * productDetail.product?.sell_price)}
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* Price Info */}
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4">
                                                        <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Harga Beli</p>
                                                        <p className="text-lg font-semibold text-slate-800 dark:text-white">
                                                            {formatCurrency(productDetail.product?.buy_price)}
                                                        </p>
                                                    </div>
                                                    <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-4">
                                                        <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Harga Jual</p>
                                                        <p className="text-lg font-semibold text-slate-800 dark:text-white">
                                                            {formatCurrency(productDetail.product?.sell_price)}
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* Movement Summary */}
                                                <div>
                                                    <h4 className="font-semibold text-slate-800 dark:text-white mb-3">Ringkasan Pergerakan</h4>
                                                    <div className="grid grid-cols-3 gap-4">
                                                        <div className="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                                            <IconArrowUp className="w-5 h-5 mx-auto text-green-600 dark:text-green-400" />
                                                            <p className="text-xs text-slate-500 mt-1">Total Masuk</p>
                                                            <p className="font-semibold text-green-600 dark:text-green-400">
                                                                +{formatNumber(productDetail.movementSummary?.total_in)}
                                                            </p>
                                                        </div>
                                                        <div className="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                                            <IconArrowDown className="w-5 h-5 mx-auto text-red-600 dark:text-red-400" />
                                                            <p className="text-xs text-slate-500 mt-1">Total Keluar</p>
                                                            <p className="font-semibold text-red-600 dark:text-red-400">
                                                                -{formatNumber(productDetail.movementSummary?.total_out)}
                                                            </p>
                                                        </div>
                                                        <div className="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                                            <IconAdjustments className="w-5 h-5 mx-auto text-blue-600 dark:text-blue-400" />
                                                            <p className="text-xs text-slate-500 mt-1">Koreksi</p>
                                                            <p className="font-semibold text-blue-600 dark:text-blue-400">
                                                                {formatNumber(productDetail.movementSummary?.total_corrections)}x
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Recent Adjustments */}
                                                {productDetail.product?.inventory_adjustments?.length > 0 && (
                                                    <div>
                                                        <h4 className="font-semibold text-slate-800 dark:text-white mb-3">Riwayat Penyesuaian Terbaru</h4>
                                                        <div className="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                                                            <table className="w-full text-sm">
                                                                <thead className="bg-slate-50 dark:bg-slate-700/50">
                                                                    <tr>
                                                                        <th className="px-3 py-2 text-left text-xs font-medium text-slate-500">Tanggal</th>
                                                                        <th className="px-3 py-2 text-left text-xs font-medium text-slate-500">Tipe</th>
                                                                        <th className="px-3 py-2 text-right text-xs font-medium text-slate-500">Perubahan</th>
                                                                        <th className="px-3 py-2 text-left text-xs font-medium text-slate-500">Oleh</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                                                                    {productDetail.product.inventory_adjustments.slice(0, 10).map(adj => (
                                                                        <tr key={adj.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                                            <td className="px-3 py-2 text-slate-600 dark:text-slate-400">
                                                                                {formatDateTime(adj.created_at)}
                                                                            </td>
                                                                            <td className="px-3 py-2">
                                                                                {getAdjustmentTypeBadge(adj.type)}
                                                                            </td>
                                                                            <td className={`px-3 py-2 text-right font-medium ${
                                                                                adj.quantity_change > 0 ? 'text-green-600' : 'text-red-600'
                                                                            }`}>
                                                                                {adj.quantity_change > 0 ? '+' : ''}{formatNumber(adj.quantity_change)}
                                                                            </td>
                                                                            <td className="px-3 py-2 text-slate-600 dark:text-slate-400">
                                                                                {adj.user?.name || 'System'}
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <div className="text-center py-12 text-slate-500">
                                                Gagal memuat data
                                            </div>
                                        )}
                                    </div>

                                    {/* Modal Footer */}
                                    <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                                        {productDetail?.product && (
                                            <Link
                                                href={route('inventory-adjustments.product-history', productDetail.product.id)}
                                                className="px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                                            >
                                                Lihat Riwayat Lengkap
                                            </Link>
                                        )}
                                        <button
                                            onClick={closeDetailModal}
                                            className="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-lg transition-colors"
                                        >
                                            Tutup
                                        </button>
                                    </div>
                                </Dialog.Panel>
                            </Transition.Child>
                        </div>
                    </div>
                </Dialog>
            </Transition>
        </>
    );
}

Inventories.layout = (page) => <DashboardLayout>{page}</DashboardLayout>;
