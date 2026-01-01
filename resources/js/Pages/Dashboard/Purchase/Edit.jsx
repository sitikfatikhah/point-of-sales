import React, { useEffect, useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, useForm, usePage } from "@inertiajs/react";
import Card from "@/Components/Dashboard/Card";
import Button from "@/Components/Dashboard/Button";
import Input from "@/Components/Dashboard/Input";
import Textarea from "@/Components/Dashboard/TextArea";
import toast from "react-hot-toast";
import InputSelect from "@/Components/Dashboard/InputSelect";

export default function Edit({ purchases }) {
    const { errors } = usePage().props;

    const { data, setData, put, processing } = useForm({
        id: purchases?.id ?? "",
        barcode: purchases?.barcode ?? "",
        quantity: purchases?.quantity ?? "",
        notes: purchases?.notes ?? "",
    });

    const [selectedPurchase, setSelectedPurchase] = useState(null);

    const setSelectedPurchaseHandler = (value) => {
        setSelectedPurchase(value);
        setData("barcode", value?.id ?? "");
    };

    useEffect(() => {
        if (purchases) {
            setSelectedPurchase(purchases);
        }
    }, [purchases]);

    const submit = (e) => {
        e.preventDefault();

        put(route("purchases.update", data.id), {
            onSuccess: () => {
                toast.success("Data berhasil diperbarui 🎉", {
                    style: {
                        borderRadius: "10px",
                        background: "#1C1F29",
                        color: "#fff",
                    },
                });
            },
            onError: () => {
                toast.error("Terjadi kesalahan dalam pembaruan data", {
                    style: {
                        borderRadius: "10px",
                        background: "#FF0000",
                        color: "#fff",
                    },
                });
            },
        });
    };

    return (
        <>
            <Head title="Edit Pembelian" />

            <form onSubmit={submit}>
                <Card title="Edit Data Pembelian">

                    {/* INPUT PRODUK - jika purchases adalah satu item */}
                    <InputSelect
                        label="Produk"
                        placeholder="Pilih produk..."
                        value={selectedPurchase}
                        options={[purchases]} // karena hanya satu item
                        getOptionLabel={(option) => option.name}
                        getOptionValue={(option) => option.id}
                        onChange={setSelectedPurchaseHandler}
                        error={errors.barcode}
                    />

                    <div className="mt-4">
                        <Input
                            type="number"
                            label="Jumlah"
                            value={data.quantity}
                            onChange={(e) => setData("quantity", e.target.value)}
                            errors={errors.quantity}
                            placeholder="Masukkan jumlah produk"
                        />
                    </div>

                    <div className="mt-4">
                        <Textarea
                            label="Catatan"
                            value={data.notes}
                            onChange={(e) => setData("notes", e.target.value)}
                            errors={errors.notes}
                            placeholder="Masukkan catatan pembelian"
                        />
                    </div>

                    <div className="flex justify-end gap-2 mt-4">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            Batal
                        </Button>

                        <Button type="submit" disabled={processing}>
                            Simpan Perubahan
                        </Button>
                    </div>

                </Card>
            </form>
        </>
    );
}

Edit.layout = (page) => <DashboardLayout children={page} />;
