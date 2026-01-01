import React, { useState } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import { Head, useForm, usePage } from '@inertiajs/react'
import Card from '@/Components/Dashboard/Card'
import Button from '@/Components/Dashboard/Button'
import { IconPencilPlus, IconShoppingCartPlus } from '@tabler/icons-react'
import Input from '@/Components/Dashboard/Input'
import Textarea from '@/Components/Dashboard/TextArea'
import InputSelect from '@/Components/Dashboard/InputSelect'
import toast from 'react-hot-toast'

export default function Create({ purchases }) {
    const { errors } = usePage().props

    const { data, setData, post, processing, reset } = useForm({
        image: null,
        purchase_id: null,
        reference_number: '',
        supplier_name: '',
        purchase_date: '',
        barcode: '',
        warehouse_location: '',
        quantity: '',
        batch_number: '',
        expired: '',
        currency: '',
        total_price: '',
        discount_percent: '',
        tax_included: false,
        tax_percent: '',
        payment_method: '',
        status: '',
        notes: '',
    })

    const [selectedPurchase, setSelectedPurchase] = useState(null)

    const handleSelectPurchase = (value) => {
        setSelectedPurchase(value)
        setData('purchase_id', value?.id ?? null)
    }

    const handleImageChange = (e) => {
        setData('image', e.target.files[0])
    }

    // ⬇️ TANPA event
    const submit = () => {
        post(route('purchase.store'), {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Pembelian berhasil disimpan 🎉')
                reset()
                setSelectedPurchase(null)
            },
            onError: () => {
                toast.error('Gagal menyimpan pembelian')
            },
        })
    }

    return (
        <>
            <Head title="Tambah Produk" />

            <Card
                title="Tambah Produk"
                icon={<IconShoppingCartPlus size={20} strokeWidth={1.5} />}
                footer={
                    <Button
                        type="button"
                        onClick={submit}
                        label={processing ? 'Menyimpan...' : 'Simpan'}
                        disabled={processing}
                        icon={<IconPencilPlus size={20} strokeWidth={1.5} />}
                    />
                }
            >
                <div className="grid grid-cols-12 gap-4">

                    {/* Upload */}
                    <div className="col-span-12">
                        <Input
                            type="file"
                            label="Bukti / Gambar"
                            onChange={handleImageChange}
                            errors={errors.image}
                        />
                    </div>

                    {/* Referensi */}
                    <div className="col-span-12">
                        <InputSelect
                            label="Referensi Produk (opsional)"
                            data={purchases?.data ?? []}
                            selected={selectedPurchase}
                            setSelected={handleSelectPurchase}
                            placeholder="Pilih Produk"
                            displayKey="reference_number"
                            searchable
                        />
                    </div>

                    {/* Supplier */}
                    <div className="col-span-6">
                        <Input
                            label="Supplier"
                            value={data.supplier_name}
                            onChange={e => setData('supplier_name', e.target.value)}
                            errors={errors.supplier_name}
                        />
                    </div>

                    {/* Tanggal */}
                    <div className="col-span-6">
                        <Input
                            type="date"
                            label="Tanggal Produk"
                            value={data.purchase_date}
                            onChange={e => setData('purchase_date', e.target.value)}
                            errors={errors.purchase_date}
                        />
                    </div>

                    {/* Barcode */}
                    <div className="col-span-6">
                        <Input
                            label="Barcode"
                            value={data.barcode}
                            onChange={e => setData('barcode', e.target.value)}
                        />
                    </div>

                    {/* Gudang */}
                    <div className="col-span-6">
                        <Input
                            label="Lokasi Gudang"
                            value={data.warehouse_location}
                            onChange={e => setData('warehouse_location', e.target.value)}
                        />
                    </div>

                    {/* Qty */}
                    <div className="col-span-4">
                        <Input
                            type="number"
                            label="Quantity"
                            value={data.quantity}
                            onChange={e => setData('quantity', e.target.value)}
                        />
                    </div>
                    <div className="col-span-4">
                        <Input
                            type="number"
                            label="Harga Beli"
                            min={0}
                            value={data.purchase_price}
                            onChange={e => setData('quantity', e.target.value)}
                        />
                    </div>
                    <div className="col-span-4">
                        <Input
                            type="number"
                            label="Diskon"
                            min={0}
                            value={data.discount_percent}
                            onChange={e => setData('quantity', e.target.value)}
                        />
                    </div>

                    {/* Batch */}
                    <div className="col-span-4">
                        <Input
                            label="Batch Number"
                            value={data.batch_number}
                            onChange={e => setData('batch_number', e.target.value)}
                        />
                    </div>

                    {/* Expired */}
                    <div className="col-span-4">
                        <Input
                            type="date"
                            label="Tanggal Expired"
                            value={data.expiration_date}
                            onChange={e => setData('expiration_date', e.target.value)}
                        />
                    </div>

                    {/* Currency */}
                    <div className="col-span-4">
                        <Input
                            label="Currency"
                            value={data.currency}
                            onChange={e => setData('currency', e.target.value)}
                        />
                    </div>

                    {/* Exchange */}
                    <div className="col-span-4">
                        <Input
                            type="number"
                            label="Exchange Rate"
                            value={data.exchange_rate}
                            onChange={e => setData('exchange_rate', e.target.value)}
                        />
                    </div>

                    {/* Total */}
                    <div className="col-span-4">
                        <Input
                            type="number"
                            label="Total Harga"
                            value={data.total_price}
                            onChange={e => setData('total_price', e.target.value)}
                        />
                    </div>

                    {/* Tax Included */}
                    <div className="col-span-6 flex items-center gap-3 mt-6">
                        <input
                            type="checkbox"
                            checked={data.tax_included}
                            onChange={e => setData('tax_included', e.target.checked)}
                            className="rounded border-gray-300 text-teal-600"
                        />
                        <span className="text-sm text-gray-600">
                            Harga termasuk pajak
                        </span>
                    </div>

                    {/* Pajak */}
                    <div className="col-span-6">
                        <Input
                            type="number"
                            label="Jumlah Pajak"
                            value={data.tax_amount}
                            onChange={e => setData('tax_amount', e.target.value)}
                        />
                    </div>

                    {/* Serial */}
                    <div className="col-span-12">
                        <Textarea
                            label="Serial Numbers"
                            value={data.serial_numbers}
                            onChange={e => setData('serial_numbers', e.target.value)}
                        />
                    </div>

                    {/* Notes */}
                    <div className="col-span-12">
                        <Textarea
                            label="Catatan"
                            value={data.notes}
                            onChange={e => setData('notes', e.target.value)}
                        />
                    </div>

                </div>
</Card>

        </>
    )
}

Create.layout = page => <DashboardLayout>{page}</DashboardLayout>
