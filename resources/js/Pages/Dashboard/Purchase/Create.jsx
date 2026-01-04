import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import Card from '@/Components/Dashboard/Card';
import Input from '@/Components/Dashboard/Input';
import Textarea from '@/Components/Dashboard/TextArea';
import Checkbox from '@/Components/Dashboard/Checkbox';
import Table from '@/Components/Dashboard/Table';
import Swal from 'sweetalert2';
import toast from 'react-hot-toast';
import { IconPlus, IconTrash, IconSearch, IconArrowLeft, IconDeviceFloppy } from '@tabler/icons-react';

export default function Create({ products }) {
    const { errors } = usePage().props;

    const { data, setData, post } = useForm({
        supplier_name: '',
        purchase_date: new Date().toISOString().slice(0, 10),
        status: 'received',
        notes: '',
        reference: '',
        tax_included: false,
        items: [],
    });

    const [cartItems, setCartItems] = useState([
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
        setCartItems(prev => prev.filter(item => !item.checked));
    };

    const calculateTotal = (item, taxIncluded = false) => {
        const quantity = Number(item.quantity) || 0;
        const price = Number(item.purchase_price) || 0;
        const discount = Number(item.discount_percent) || 0;
        const tax = Number(item.tax_percent) || 0;

        // subtotal
        let total = quantity * price;

        // diskon
        total -= total * (discount / 100);

        // ✅ JIKA PPN DICENTANG → TAMBAHKAN PAJAK
        if (taxIncluded && tax > 0) {
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

    const handleSearchProduct = index => {
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
    };

    const [isSaving, setIsSaving] = useState(false);

   const handleSubmit = (e) => {
    e.preventDefault();
    if (isSaving) return;

        setIsSaving(true);

        router.post(route('purchase.store'), {
            supplier_name: data.supplier_name,
            purchase_date: data.purchase_date,
            notes: data.notes,
            tax_included: data.tax_included,
            status: data.status || 'received',
            reference: data.reference,

            items: cartItems.map(item => ({
                barcode: item.product?.barcode ?? item.barcode,
                quantity: Number(item.quantity) || 0,
                purchase_price: Number(item.purchase_price) || 0,
                total_price: calculateTotal(item, data.tax_included),
                tax_percent: Number(item.tax_percent) || 0,
                discount_percent: Number(item.discount_percent) || 0,
                warehouse: item.warehouse ?? 'main',
                batch: item.batch ?? null,
                expired: item.expired ?? null,
                currency: item.currency ?? 'IDR',
            })),
        }, {
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data berhasil disimpan!',
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



    const handleEnterKey = (e, index, field) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (field === 'barcode') handleSearchProduct(index);
            else if (index === cartItems.length - 1) addRow();
        }
    };

    const formatCurrency = (value) => {
        const num = Number(value);
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(isNaN(num) ? 0 : num);
    };

    const calculateGrandTotal = () => {
        return cartItems.reduce((sum, item) => sum + calculateTotal(item, data.tax_included), 0);
    };

    const calculateSubtotal = () => {
        return cartItems.reduce((sum, item) => {
            const quantity = Number(item.quantity) || 0;
            const price = Number(item.purchase_price) || 0;
            const discount = Number(item.discount_percent) || 0;
            let subtotal = quantity * price;
            subtotal -= subtotal * (discount / 100);
            return sum + subtotal;
        }, 0);
    };

    const calculateTotalPPN = () => {
        if (!data.tax_included) return 0;
        return cartItems.reduce((sum, item) => {
            const quantity = Number(item.quantity) || 0;
            const price = Number(item.purchase_price) || 0;
            const discount = Number(item.discount_percent) || 0;
            const tax = Number(item.tax_percent) || 0;
            let subtotal = quantity * price;
            subtotal -= subtotal * (discount / 100);
            return sum + (subtotal * (tax / 100));
        }, 0);
    };
    
    const totalPPN = Math.round(calculateTotalPPN());

        if (totalPPN > 0 && !data.tax_included) {
            toast.error('Centang "Termasuk PPN" karena ada nilai PPN');
            return;
        }


    const statusOptions = [
        { value: 'pending', label: 'Pending' },
        { value: 'paid', label: 'Paid' },
        { value: 'received', label: 'Received' },
    ];

    return (
        <>
            <Head title="Tambah Pembelian" />

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
                        {isSaving ? 'Menyimpan...' : 'Simpan Pembelian'}
                    </button>
                </div>

                {/* Purchase Info Card */}
                <Card title="Informasi Pembelian">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Input
                            label="Supplier"
                            value={data.supplier_name}
                            onChange={e => setData('supplier_name', e.target.value)}
                            errors={errors.supplier_name}
                            placeholder="Nama supplier"
                        />
                        <Input
                            label="Reference"
                            value={data.reference}
                            onChange={e => setData('reference', e.target.value)}
                            errors={errors.reference}
                            placeholder="Nomor referensi"
                        />
                        <Input
                            label="Tanggal Pembelian"
                            type="date"
                            value={data.purchase_date}
                            onChange={e => setData('purchase_date', e.target.value)}
                            errors={errors.purchase_date}
                        />
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Status
                            </label>
                            <select
                                value={data.status}
                                onChange={e => setData('status', e.target.value)}
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
                                onChange={e => setData('notes', e.target.value)}
                                errors={errors.notes}
                                placeholder="Catatan pembelian (opsional)"
                                rows={3}
                            />
                        </div>
                        <div className="flex items-center">
                            <Checkbox
                                label="Termasuk PPN"
                                checked={data.tax_included}
                                disabled={Math.round(calculateTotalPPN()) > 0}
                                onChange={e => setData('tax_included', e.target.checked)}
                            />
                            {Math.round(calculateTotalPPN()) > 0 && (
                                <span className="text-xs text-red-500">
                                    * wajib
                                </span>
                            )}
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
                                    <Table.Th className="w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={cartItems.length > 0 && cartItems.every(item => item.checked)}
                                            onChange={(e) => {
                                                setCartItems(prev => prev.map(item => ({ ...item, checked: e.target.checked })));
                                            }}
                                            className="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
                                        />
                                    </Table.Th>
                                    <Table.Th className="w-[180px]">Barcode</Table.Th>
                                    <Table.Th className="min-w-[200px]">Produk</Table.Th>
                                    <Table.Th className="w-[100px] text-center">Qty</Table.Th>
                                    <Table.Th className="w-[100px] text-center">warehouse</Table.Th>
                                    <Table.Th className="w-[100px] text-center">Batch</Table.Th>
                                    <Table.Th className="w-[100px] text-center">Expired</Table.Th>
                                    <Table.Th className="w-[140px] text-center">Harga</Table.Th>
                                    <Table.Th className="w-[100px] text-center">Diskon %</Table.Th>
                                    <Table.Th className="w-[90px] text-center">PPN %</Table.Th>
                                    <Table.Th className="w-[150px] text-right">Total</Table.Th>
                                    <Table.Th className="w-12"></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {cartItems.map((item, index) => (
                                    <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <Table.Td className="text-center">
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
                                                    placeholder="Scan barcode"
                                                    className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => handleSearchProduct(index)}
                                                    className="p-1.5 rounded-md bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600 shrink-0"
                                                >
                                                    <IconSearch size={16} />
                                                </button>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <span className="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                                                {item.product?.description || '-'}
                                            </span>
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="1"
                                                value={item.quantity}
                                                onChange={(e) => updateRow(index, 'quantity', Number(e.target.value) || 1)}
                                                onKeyDown={blockMinus}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="text"
                                                value={item.warehouse ?? ''}
                                                onChange={(e) => updateRow(index, 'warehouse', e.target.value)}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
                                            />
                                            
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="text"
                                                value={item.batch ?? ''}
                                                onChange={(e) => updateRow(index, 'batch', e.target.value)}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
                                            />
                                            
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="text"
                                                value={item.expired ?? ''}
                                                onChange={(e) => updateRow(index, 'expired', e.target.value)}
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
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
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
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
                                                className="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-md bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-center"
                                            />
                                        </Table.Td>
                                        <Table.Td className="text-right font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                            {formatCurrency(calculateTotal(item, data.tax_included))}
                                        </Table.Td>
                                        <Table.Td className="text-center">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (cartItems.length === 1) {
                                                        toast.error('Minimal harus ada 1 item');
                                                        return;
                                                    }
                                                    setCartItems(prev => prev.filter((_, i) => i !== index));
                                                }}
                                                className="p-1.5 rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                                            >
                                                <IconTrash size={16} />
                                            </button>
                                        </Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </div>

                    {/* Summary */}
                    <div className="mt-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg space-y-3">
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-slate-600 dark:text-slate-400">
                                Jumlah Item
                            </span>
                            <span className="font-medium text-slate-700 dark:text-slate-300">
                                {cartItems.filter(item => item.barcode).length} item
                            </span>
                        </div>
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-slate-600 dark:text-slate-400">
                                Subtotal (sebelum PPN)
                            </span>
                            <span className="font-medium text-slate-700 dark:text-slate-300">
                                {formatCurrency(calculateSubtotal())}
                            </span>
                        </div>
                        {data.tax_included && (
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-slate-600 dark:text-slate-400">
                                    Total PPN
                                </span>
                                <span className="font-medium text-slate-700 dark:text-slate-300">
                                    {formatCurrency(Math.round(calculateTotalPPN()))}
                                </span>
                            </div>
                        )}
                        <div className="pt-3 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center">
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

Create.layout = page => <DashboardLayout>{page}</DashboardLayout>;
