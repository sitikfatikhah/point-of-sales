import React, { useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, router, usePage } from '@inertiajs/react';
import Card from '@/Components/Dashboard/Card';
import Input from '@/Components/Dashboard/Input';
import Textarea from '@/Components/Dashboard/TextArea';
import Checkbox from '@/Components/Dashboard/Checkbox';
import Table from '@/Components/Dashboard/Table';
import Swal from 'sweetalert2';
import toast from 'react-hot-toast';
import { IconPlus, IconTrash, IconSearch, IconArrowLeft, IconDeviceFloppy } from '@tabler/icons-react';

export default function Edit({ purchase, products }) {
    const { errors } = usePage().props;

    const [data, setData] = useState({
        supplier_name: purchase?.supplier_name || '',
        purchase_date: purchase?.purchase_date || new Date().toISOString().slice(0, 10),
        status: purchase?.status || 'received',
        notes: purchase?.notes || '',
        reference: purchase?.reference || '',
        tax_included: purchase?.tax_included || false,
    });

    const [cartItems, setCartItems] = useState(
        purchase?.items?.map((item, index) => ({
            id: index + 1,
            product: item.product || null,
            barcode: item.barcode || '',
            quantity: item.quantity || 1,
            warehouse: item.warehouse || '',
            batch: item.batch || '',
            expired: item.expired || '',
            currency: item.currency || 'IDR',
            purchase_price: item.purchase_price || 0,
            tax_percent: item.tax_percent || 0,
            discount_percent: item.discount_percent || 0,
            checked: false,
        })) || [{
            id: Date.now(),
            product: null,
            barcode: '',
            quantity: 1,
            warehouse: '',
            batch: '',
            expired: '',
            currency: 'IDR',
            purchase_price: 0,
            tax_percent: 0,
            discount_percent: 0,
            checked: false,
        }]
    );

    const [isSaving, setIsSaving] = useState(false);

    const statusOptions = [
        { value: 'pending', label: 'Pending' },
        { value: 'paid', label: 'Paid' },
        { value: 'received', label: 'Received' },
    ];

    const handleDataChange = (field, value) => {
        setData(prev => ({ ...prev, [field]: value }));
    };

    const addRow = () => {
        setCartItems(prev => [
            ...prev,
            {
                id: Date.now(),
                product: null,
                barcode: '',
                quantity: 1,
                warehouse: '',
                batch: '',
                expired: '',
                currency: 'IDR',
                purchase_price: 0,
                tax_percent: 0,
                discount_percent: 0,
                checked: false,
            },
        ]);
    };

    const removeCheckedRows = () => {
        const remaining = cartItems.filter(item => !item.checked);
        if (remaining.length === 0) {
            toast.error('Minimal harus ada 1 item');
            return;
        }
        setCartItems(remaining);
    };

    const calculateTotal = (item) => {
        const quantity = Number(item.quantity) || 0;
        const price = Number(item.purchase_price) || 0;
        const discount = Number(item.discount_percent) || 0;
        const tax = Number(item.tax_percent) || 0;

        let total = quantity * price;
        total -= total * (discount / 100);

        if (data.tax_included && tax > 0) {
            total += total * (tax / 100);
        }

        return Math.round(Math.max(total, 0));
    };

    const updateRow = (index, field, value) => {
        setCartItems(prev =>
            prev.map((item, i) => i === index ? { ...item, [field]: value } : item)
        );
    };

    const handleNumberChange = (index, field) => (e) => {
        const value = e.target.value;
        updateRow(index, field, value === '' ? 0 : Number(value));
    };

    const blockMinus = (e) => { if (e.key === '-') e.preventDefault(); };

    const handleSearchProduct = (index) => {
        const barcodeToSearch = cartItems[index].barcode;
        if (!barcodeToSearch) return;

        const product = products.find(p => p.barcode === barcodeToSearch);
        if (!product) {
            toast.error('Produk tidak ditemukan');
            return;
        }

        setCartItems(prev => {
            const updated = [...prev];
            updated[index] = {
                ...updated[index],
                product,
                barcode: product.barcode,
                quantity: updated[index].quantity || 1,
            };
            return updated;
        });
        toast.success(`Produk "${product.description}" ditemukan`);
    };

    const handleEnterKey = (e, index, field) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (field === 'barcode') handleSearchProduct(index);
            else if (index === cartItems.length - 1) addRow();
        }
    };

    const calculateGrandTotal = () => {
        return cartItems.reduce((sum, item) => sum + calculateTotal(item), 0);
    };

    const formatCurrency = (value) => {
        const num = Number(value);
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(isNaN(num) ? 0 : num);
    };

    const calculateTotalPPN = () => {
        return cartItems.reduce((sum, item) => {
            const qty = Number(item.quantity) || 0;
            const price = Number(item.purchase_price) || 0;
            const discount = Number(item.discount_percent) || 0;
            const tax = Number(item.tax_percent) || 0;

            let subtotal = qty * price;
            subtotal -= subtotal * (discount / 100);

            return sum + (subtotal * (tax / 100));
        }, 0);
    };
    useEffect(() => {
        if (calculateTotalPPN() > 0 && !data.tax_included) {
            setData(prev => ({ ...prev, tax_included: true }));
        }
    }, [cartItems]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isSaving) return;

        // Validate items
        const hasValidItems = cartItems.some(item => item.barcode && item.quantity > 0);
        if (!hasValidItems) {
            toast.error('Minimal harus ada 1 item dengan barcode dan quantity valid');
            return;
        }

        setIsSaving(true);

        router.put(route('purchase.update', purchase.id), {
            supplier_name: data.supplier_name,
            purchase_date: data.purchase_date,
            notes: data.notes,
            tax_included: data.tax_included,
            status: data.status || 'received',
            reference: data.reference,
            items: cartItems
                .filter(item => item.barcode)
                .map(item => ({
                    barcode: item.product?.barcode ?? item.barcode,
                    description: item.product?.description || '',
                    quantity: Number(item.quantity) || 0,
                    purchase_price: Number(item.purchase_price) || 0,
                    total_price: calculateTotal(item),
                    tax_percent: Number(item.tax_percent) || 0,
                    discount_percent: Number(item.discount_percent) || 0,
                    warehouse: item.warehouse || 'main',
                    batch: item.batch || null,
                    expired: item.expired || null,
                    currency: item.currency || 'IDR',
                })),
        }, {
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data pembelian berhasil diperbarui!',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            onError: (errors) => {
                console.error(errors);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Cek kembali inputan Anda!',
                });
            },
            onFinish: () => setIsSaving(false),
        });
    };

    return (
        <>
            <Head title="Edit Pembelian" />

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <button
                        type="button"
                        onClick={() => router.visit(route('purchase.index'))}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors"
                    >
                        <IconArrowLeft size={18} />
                        Kembali
                    </button>
                    <button
                        type="submit"
                        disabled={isSaving}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <IconDeviceFloppy size={18} />
                        {isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </div>

                {/* Purchase Info Card */}
                <Card title="Informasi Pembelian">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Input
                            label="Supplier"
                            value={data.supplier_name}
                            onChange={e => handleDataChange('supplier_name', e.target.value)}
                            errors={errors.supplier_name}
                            placeholder="Nama supplier"
                        />
                        <Input
                            label="Reference"
                            value={data.reference}
                            onChange={e => handleDataChange('reference', e.target.value)}
                            errors={errors.reference}
                            placeholder="Nomor referensi"
                        />
                        <Input
                            label="Tanggal Pembelian"
                            type="date"
                            value={data.purchase_date}
                            onChange={e => handleDataChange('purchase_date', e.target.value)}
                            errors={errors.purchase_date}
                        />
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Status
                            </label>
                            <select
                                value={data.status}
                                onChange={e => handleDataChange('status', e.target.value)}
                                className="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg
                                    bg-white text-slate-900
                                    dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100
                                    focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:focus:border-blue-400
                                    transition-colors"
                            >
                                {statusOptions.map(option => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-2 lg:col-span-2">
                            <Textarea
                                label="Catatan"
                                value={data.notes}
                                onChange={e => handleDataChange('notes', e.target.value)}
                                errors={errors.notes}
                                placeholder="Catatan pembelian (opsional)"
                                rows={3}
                            />
                        </div>
                        <div className="flex items-center">
                            <Checkbox
                                label="Termasuk PPN"
                                checked={data.tax_included}
                                onChange={e => handleDataChange('tax_included', e.target.checked)}
                            />
                        </div>
                    </div>
                </Card>

                {/* Items Card */}
                <Card title="Detail Produk">
                    <div className="flex flex-wrap gap-2 mb-4">
                        <button
                            type="button"
                            onClick={addRow}
                            className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                        >
                            <IconPlus size={18} />
                            Tambah Baris
                        </button>
                        <button
                            type="button"
                            onClick={removeCheckedRows}
                            disabled={!cartItems.some(item => item.checked)}
                            className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <IconTrash size={18} />
                            Hapus Terpilih
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className="w-10">
                                        <input
                                            type="checkbox"
                                            checked={cartItems.length > 0 && cartItems.every(item => item.checked)}
                                            onChange={(e) => {
                                                setCartItems(prev => prev.map(item => ({ ...item, checked: e.target.checked })));
                                            }}
                                            className="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
                                        />
                                    </Table.Th>
                                    <Table.Th className="min-w-[150px]">Barcode</Table.Th>
                                    <Table.Th className="min-w-[120px]">Produk</Table.Th>
                                    <Table.Th className="w-24">Qty</Table.Th>
                                    <Table.Th className="w-32">Harga</Table.Th>
                                    <Table.Th className="w-20">Diskon %</Table.Th>
                                    <Table.Th className="w-20">PPN %</Table.Th>
                                    <Table.Th className="w-32 text-right">Total</Table.Th>
                                    <Table.Th className="w-10"></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {cartItems.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <Table.Td>
                                            <input
                                                type="checkbox"
                                                checked={item.checked}
                                                onChange={(e) => updateRow(index, 'checked', e.target.checked)}
                                                className="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex gap-1">
                                                <input
                                                    type="text"
                                                    value={item.barcode}
                                                    onChange={(e) => updateRow(index, 'barcode', e.target.value)}
                                                    onKeyDown={(e) => handleEnterKey(e, index, 'barcode')}
                                                    placeholder="Scan/ketik barcode"
                                                    className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => handleSearchProduct(index)}
                                                    className="p-1.5 rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600"
                                                >
                                                    <IconSearch size={16} />
                                                </button>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <span className="text-sm text-slate-600 dark:text-slate-400">
                                                {item.product?.description || '-'}
                                            </span>
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="0"
                                                value={item.quantity}
                                                onChange={handleNumberChange(index, 'quantity')}
                                                onKeyDown={blockMinus}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-right"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="0"
                                                value={item.purchase_price}
                                                onChange={handleNumberChange(index, 'purchase_price')}
                                                onKeyDown={blockMinus}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-right"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={item.discount_percent}
                                                onChange={handleNumberChange(index, 'discount_percent')}
                                                onKeyDown={blockMinus}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-right"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={item.tax_percent}
                                                onChange={handleNumberChange(index, 'tax_percent')}
                                                onKeyDown={blockMinus}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-right"
                                            />
                                        </Table.Td>
                                        <Table.Td className="text-right font-medium text-slate-900 dark:text-slate-100">
                                            {formatCurrency(calculateTotal(item))}
                                        </Table.Td>
                                        <Table.Td>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (cartItems.length === 1) {
                                                        toast.error('Minimal harus ada 1 item');
                                                        return;
                                                    }
                                                    setCartItems(prev => prev.filter((_, i) => i !== index));
                                                }}
                                                className="p-1 rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                                            >
                                                <IconTrash size={16} />
                                            </button>
                                        </Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </div>

                    {/* Grand Total */}
                    <div className="mt-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                        <div className="flex justify-between items-center">
                            <span className="text-lg font-semibold text-slate-700 dark:text-slate-300">
                                Grand Total
                            </span>
                            <span className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                {formatCurrency(calculateGrandTotal())}
                            </span>
                        </div>
                    </div>
                </Card>
            </form>
        </>
    );
}

Edit.layout = (page) => <DashboardLayout children={page} />;
