import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import Card from '@/Components/Dashboard/Card';
import PrimaryButton from '@/Components/PrimaryButton';
import Input from '@/Components/Dashboard/Input';
import Textarea from '@/Components/Dashboard/TextArea';
import Checkbox from '@/Components/Dashboard/Checkbox';
import InputSelect from '@/Components/Dashboard/InputSelect';
import Table from '@/Components/Dashboard/Table';
import Swal from 'sweetalert2';
import { ChevronDown } from 'lucide-react';
import Dropdown from '@/Components/Dropdown';
import toast from 'react-hot-toast';
import { router } from '@inertiajs/react';

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

    const statusLabel = {
        pending: 'Pending',
        paid: 'Paid',
        received: 'Received',
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

    return (
        <>
            <Head title="Tambah Pembelian" />

            <form onSubmit={handleSubmit} className="space-y-6">
                <Card title="Informasi Pembelian">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Input
                            label="Supplier"
                            value={data.supplier_name}
                            onChange={e => setData('supplier_name', e.target.value)}
                            error={errors.supplier_name}
                        />
                        <Input
                            label="Reference"
                            value={data.reference}
                            onChange={e => setData('reference', e.target.value)}
                        />
                        <Input
                            label="Tanggal"
                            type="date"
                            value={data.purchase_date}
                            onChange={e => setData('purchase_date', e.target.value)}
                            error={errors.purchase_date}
                        />
                        <div>
                            <label className="block text-gray-700 pb-1">Status</label>
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="w-full flex justify-between items-center border px-3 py-1 rounded-md text-gray-700"
                                    >
                                        {data.status ? statusLabel[data.status] : 'Pilih status'}
                                        <ChevronDown size={16} />
                                    </button>
                                </Dropdown.Trigger>
                                <Dropdown.Content className="w-full">
                                    {Object.keys(statusLabel).map(status => (
                                        <button
                                            key={status}
                                            type="button"
                                            className="w-full text-left text-gray-700 px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                                            onClick={() => setData('status', status)}
                                        >
                                            {statusLabel[status]}
                                        </button>
                                    ))}
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                        <Textarea
                            label="Catatan"
                            value={data.notes}
                            onChange={e => setData('notes', e.target.value)}
                        />
                        <Checkbox
                            className="justify-end"
                            label="PPN"
                            checked={data.tax_included}
                            onChange={e => setData('tax_included', e.target.checked)}
                            error={errors.tax_included}
                        />
                    </div>
                </Card>

                <Card title="Detail Produk">
                    <PrimaryButton
                        type="button"
                        onClick={addRow}
                        className="flex flex-col justify-end bg-blue-500 hover:bg-blue-600 p-3 mx-5 my-3"
                    >
                        Tambah Baris
                    </PrimaryButton>
                    <PrimaryButton
                        type="button"
                        onClick={removeCheckedRows}
                        className="bg-red-500 hover:bg-red-600 p-3"
                        disabled={!cartItems.some(item => item.checked)}
                    >
                        Hapus Baris
                    </PrimaryButton>

                    <Table>
                        <Table.Thead>
                        <tr className="text-gray-700">
                            <Table.Th className="w-[1px]">Pilih</Table.Th>
                            <Table.Th>Barcode</Table.Th>
                            <Table.Th>Quantity</Table.Th>
                            <Table.Th>Harga</Table.Th>
                            <Table.Th>Diskon (%)</Table.Th>
                            <Table.Th>PPN (%)</Table.Th>
                            <Table.Th>Gudang</Table.Th>
                            <Table.Th>Batch</Table.Th>
                            <Table.Th>Expired</Table.Th>
                            <Table.Th>Mata Uang</Table.Th>
                            <Table.Th>Total</Table.Th>
                        </tr>
                        </Table.Thead>

                        <Table.Tbody>
                        {cartItems.map((row, index) => (
                            <tr key={row.id}>
                            <Table.Td>
                                <Checkbox
                                checked={!!row.checked}
                                onChange={e => updateRow(index, 'checked', e.target.checked)}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="text"
                                placeholder="Barcode"
                                value={row.barcode || ''}
                                onChange={e => updateRow(index, 'barcode', e.target.value)}
                                onKeyDown={e => handleEnterKey(e, index, 'barcode')}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="number"
                                min={1}
                                value={row.quantity || 1}
                                onChange={e => updateRow(index, 'quantity', Number(e.target.value))}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="number"
                                min={0}
                                value={row.purchase_price || 0}
                                onChange={e => updateRow(index, 'purchase_price', Number(e.target.value))}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="number"
                                min={0}
                                value={row.discount_percent || 0}
                                onChange={handleNumberChange(index, 'discount_percent')}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="number"
                                min={0}
                                value={row.tax_percent || 0}
                                onChange={handleNumberChange(index, 'tax_percent')}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                value={row.warehouse || ''}
                                onChange={e => updateRow(index, 'warehouse', e.target.value)}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                value={row.batch || ''}
                                onChange={e => updateRow(index, 'batch', e.target.value)}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="date"
                                value={row.expired || ''}
                                onChange={e => updateRow(index, 'expired', e.target.value)}
                                />
                            </Table.Td>

                            <Table.Td>
                                <InputSelect
                                data={['IDR', 'USD']}
                                selected={row.currency || 'IDR'}
                                setSelected={v => updateRow(index, 'currency', v)}
                                />
                            </Table.Td>

                            <Table.Td>
                                <Input
                                type="number"
                                value={calculateTotal(row, data.tax_included)}
                                readOnly
                                className="bg-gray-100"
                                />
                            </Table.Td>
                            </tr>
                        ))}
                        </Table.Tbody>

                    </Table>

                    <PrimaryButton
                        type="button"
                        onClick={handleSubmit}
                        disabled={isSaving}
                        className="flex flex-col justify-end bg-green-500 hover:bg-green-600 p-3 mx-5 my-3"
                    >
                        {isSaving ? 'Menyimpan...' : 'Simpan'}
                    </PrimaryButton>
                </Card>
            </form>
        </>
    );
}

Create.layout = page => <DashboardLayout>{page}</DashboardLayout>;
