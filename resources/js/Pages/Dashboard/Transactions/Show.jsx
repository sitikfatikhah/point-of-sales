import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ArrowLeft, User, Calendar, Receipt, CreditCard, Package, TrendingDown, TrendingUp, Clock } from 'lucide-react';
import { formatDateTime as formatDate } from '@/Utils/DateHelper';

export default function Show({ auth, transaction, inventoryAdjustments }) {
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(amount);
    };

    const getPaymentStatusBadge = (status) => {
        const statusConfig = {
            paid: { label: 'Lunas', className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
            pending: { label: 'Pending', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' },
            failed: { label: 'Gagal', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
        };
        const config = statusConfig[status] || statusConfig.pending;
        return <span className={`px-2 py-1 rounded-full text-xs font-medium ${config.className}`}>{config.label}</span>;
    };

    const getAdjustmentTypeBadge = (type) => {
        const typeConfig = {
            sale: { label: 'Penjualan', icon: TrendingDown, className: 'text-red-600 dark:text-red-400' },
            in: { label: 'Masuk', icon: TrendingUp, className: 'text-green-600 dark:text-green-400' },
            out: { label: 'Keluar', icon: TrendingDown, className: 'text-red-600 dark:text-red-400' },
        };
        const config = typeConfig[type] || typeConfig.out;
        const Icon = config.icon;
        return (
            <span className={`inline-flex items-center gap-1 ${config.className}`}>
                <Icon size={14} />
                {config.label}
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('transactions.history')}
                        className="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                    >
                        <ArrowLeft size={18} className="text-gray-600 dark:text-gray-300" />
                    </Link>
                    <div>
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Detail Transaksi
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Invoice: {transaction.invoice}</p>
                    </div>
                </div>
            }
        >
            <Head title={`Transaksi - ${transaction.invoice}`} />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Transaction Info Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                    <Receipt size={20} className="text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Invoice</p>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">{transaction.invoice}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30">
                                    <CreditCard size={20} className="text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Grand Total</p>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(transaction.grand_total)}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30">
                                    <Package size={20} className="text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Total Item</p>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">{transaction.total_items} item</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-200 dark:border-gray-700">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                                    <TrendingUp size={20} className="text-yellow-600 dark:text-yellow-400" />
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Total Profit</p>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(transaction.total_profit || 0)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Main Content Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Transaction Details */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Payment Info */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Informasi Pembayaran</h3>
                                </div>
                                <div className="p-6">
                                    <dl className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <dt className="text-sm text-gray-500 dark:text-gray-400">Metode Pembayaran</dt>
                                            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">{transaction.payment_method || 'Cash'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm text-gray-500 dark:text-gray-400">Status Pembayaran</dt>
                                            <dd className="mt-1">{getPaymentStatusBadge(transaction.payment_status)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm text-gray-500 dark:text-gray-400">Uang Diterima</dt>
                                            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{formatCurrency(transaction.cash)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm text-gray-500 dark:text-gray-400">Kembalian</dt>
                                            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{formatCurrency(transaction.change)}</dd>
                                        </div>
                                        {transaction.payment_reference && (
                                            <div className="col-span-2">
                                                <dt className="text-sm text-gray-500 dark:text-gray-400">Referensi Pembayaran</dt>
                                                <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{transaction.payment_reference}</dd>
                                            </div>
                                        )}
                                    </dl>
                                </div>
                            </div>

                            {/* Products */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Detail Produk</h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead className="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produk</th>
                                                <th className="text-center px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                                <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Harga</th>
                                                <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Diskon</th>
                                                <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {transaction.details?.map((detail, index) => (
                                                <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td className="px-6 py-4">
                                                        <div>
                                                            <p className="font-medium text-gray-900 dark:text-gray-100">{detail.product?.title || '-'}</p>
                                                            <p className="text-xs text-gray-500 dark:text-gray-400">{detail.product?.category?.name || '-'}</p>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">{detail.quantity}</td>
                                                    <td className="px-6 py-4 text-right text-sm text-gray-900 dark:text-gray-100">{formatCurrency(detail.price)}</td>
                                                    <td className="px-6 py-4 text-right text-sm text-gray-900 dark:text-gray-100">{formatCurrency(detail.discount || 0)}</td>
                                                    <td className="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {formatCurrency((detail.price * detail.quantity) - (detail.discount || 0))}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <td colSpan="4" className="px-6 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">Grand Total</td>
                                                <td className="px-6 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{formatCurrency(transaction.grand_total)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            {/* Inventory Adjustments */}
                            {inventoryAdjustments?.length > 0 && (
                                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">Perubahan Inventory</h3>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead className="bg-gray-50 dark:bg-gray-700/50">
                                                <tr>
                                                    <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produk</th>
                                                    <th className="text-center px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipe</th>
                                                    <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sebelum</th>
                                                    <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Perubahan</th>
                                                    <th className="text-right px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sesudah</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                                {inventoryAdjustments.map((adjustment, index) => (
                                                    <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                        <td className="px-6 py-4">
                                                            <div>
                                                                <p className="font-medium text-gray-900 dark:text-gray-100">{adjustment.product?.title || '-'}</p>
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">{adjustment.product?.barcode || '-'}</p>
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 text-center">{getAdjustmentTypeBadge(adjustment.type)}</td>
                                                        <td className="px-6 py-4 text-right text-sm text-gray-900 dark:text-gray-100">{parseFloat(adjustment.quantity_before)}</td>
                                                        <td className="px-6 py-4 text-right text-sm">
                                                            <span className={adjustment.quantity_change < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}>
                                                                {adjustment.quantity_change > 0 ? '+' : ''}{parseFloat(adjustment.quantity_change)}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">{parseFloat(adjustment.quantity_after)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Cashier Info */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Kasir</h3>
                                </div>
                                <div className="p-6">
                                    <div className="flex items-center gap-4">
                                        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700">
                                            <User size={24} className="text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-gray-100">{transaction.cashier?.name || '-'}</p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">{transaction.cashier?.email || '-'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Info */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Pelanggan</h3>
                                </div>
                                <div className="p-6">
                                    {transaction.customer ? (
                                        <div className="space-y-3">
                                            <div>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">Nama</p>
                                                <p className="font-medium text-gray-900 dark:text-gray-100">{transaction.customer.name}</p>
                                            </div>
                                            {transaction.customer.no_telp && (
                                                <div>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">No. Telepon</p>
                                                    <p className="text-sm text-gray-900 dark:text-gray-100">{transaction.customer.no_telp}</p>
                                                </div>
                                            )}
                                            {transaction.customer.address && (
                                                <div>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">Alamat</p>
                                                    <p className="text-sm text-gray-900 dark:text-gray-100">{transaction.customer.address}</p>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500 dark:text-gray-400">Walk-in Customer</p>
                                    )}
                                </div>
                            </div>

                            {/* Timeline */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Riwayat</h3>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-4">
                                        <div className="flex items-start gap-3">
                                            <div className="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30">
                                                <Clock size={14} className="text-green-600 dark:text-green-400" />
                                            </div>
                                            <div>
                                                <p className="font-medium text-sm text-gray-900 dark:text-gray-100">Transaksi Dibuat</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">{formatDate(transaction.created_at)}</p>
                                            </div>
                                        </div>
                                        {transaction.updated_at !== transaction.created_at && (
                                            <div className="flex items-start gap-3">
                                                <div className="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                    <Clock size={14} className="text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div>
                                                    <p className="font-medium text-sm text-gray-900 dark:text-gray-100">Terakhir Diupdate</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">{formatDate(transaction.updated_at)}</p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div className="p-6 space-y-3">
                                    <Link
                                        href={route('transactions.print', transaction.invoice)}
                                        className="block w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-center rounded-lg transition-colors"
                                    >
                                        Cetak Struk
                                    </Link>
                                    <Link
                                        href={route('transactions.history')}
                                        className="block w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-center rounded-lg transition-colors"
                                    >
                                        Kembali ke Riwayat
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
