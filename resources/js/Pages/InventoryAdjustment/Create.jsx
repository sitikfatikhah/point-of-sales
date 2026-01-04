import React, { useState, useRef, useEffect } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconArrowLeft,
    IconDeviceFloppy,
    IconSearch,
    IconPackage,
} from "@tabler/icons-react";
import toast from "react-hot-toast";

export default function Create({ products, types }) {
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [searchQuery, setSearchQuery] = useState("");
    const [showProductList, setShowProductList] = useState(false);
    const searchRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) {
                setShowProductList(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, []);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: "",
        type: "adjustment_in",
        quantity: "",
        reason: "",
        notes: "",
    });

    const filteredProducts = products?.filter(
        (product) =>
            product.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            product.barcode?.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleSelectProduct = (product) => {
        setSelectedProduct(product);
        setData("product_id", product.id);
        setSearchQuery(product.title);
        setShowProductList(false);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!data.product_id) {
            toast.error("Pilih produk terlebih dahulu");
            return;
        }

        if (!data.quantity || parseFloat(data.quantity) <= 0) {
            toast.error("Quantity harus lebih dari 0");
            return;
        }

        post(route("inventory-adjustments.store"), {
            onSuccess: () => {
                toast.success("Adjustment berhasil disimpan");
                reset();
                setSelectedProduct(null);
                setSearchQuery("");
            },
            onError: (errors) => {
                toast.error(errors.error || "Gagal menyimpan adjustment");
            },
        });
    };

    return (
        <DashboardLayout>
            <Head title="Create Inventory Adjustment" />

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
                            Buat Inventory Adjustment
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Adjustment stok manual
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Form */}
                        <div className="lg:col-span-2 space-y-6">
                            <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                                <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4">
                                    Data Adjustment
                                </h2>

                                {/* Product Search */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Produk <span className="text-red-500">*</span>
                                    </label>
                                    <div className="relative" ref={searchRef}>
                                        <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                                        <input
                                            type="text"
                                            placeholder="Cari produk berdasarkan nama atau barcode..."
                                            value={searchQuery}
                                            onChange={(e) => {
                                                setSearchQuery(e.target.value);
                                                setShowProductList(true);
                                                if (!e.target.value) {
                                                    setSelectedProduct(null);
                                                    setData("product_id", "");
                                                }
                                            }}
                                            onKeyUp={() => setShowProductList(true)}
                                            onFocus={() => setShowProductList(true)}
                                            className="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        />
                                        {showProductList && searchQuery && (
                                            <div className="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-auto">
                                                {filteredProducts?.length > 0 ? (
                                                    filteredProducts.map(
                                                        (product) => (
                                                            <button
                                                                key={product.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    handleSelectProduct(
                                                                        product
                                                                    )
                                                                }
                                                                className="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-between"
                                                            >
                                                                <div>
                                                                    <p className="font-medium text-slate-800 dark:text-white">
                                                                        {product.title}
                                                                    </p>
                                                                    <p className="text-xs text-slate-500">
                                                                        {product.barcode}
                                                                    </p>
                                                                </div>
                                                                <span className="text-sm text-slate-500">
                                                                    Stok:{" "}
                                                                    {product.stock?.toLocaleString(
                                                                        "id-ID"
                                                                    ) || 0}
                                                                </span>
                                                            </button>
                                                        )
                                                    )
                                                ) : (
                                                    <div className="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 text-center">
                                                        Produk tidak ditemukan
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    {errors.product_id && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.product_id}
                                        </p>
                                    )}
                                </div>

                                {/* Type */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Tipe Adjustment{" "}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={data.type}
                                        onChange={(e) =>
                                            setData("type", e.target.value)
                                        }
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        {Object.entries(types).map(
                                            ([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            )
                                        )}
                                    </select>
                                    {errors.type && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>

                                {/* Quantity */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        {data.type === "correction"
                                            ? "Stok Baru"
                                            : "Quantity"}{" "}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.quantity}
                                        onChange={(e) =>
                                            setData("quantity", e.target.value)
                                        }
                                        placeholder={
                                            data.type === "correction"
                                                ? "Masukkan jumlah stok baru"
                                                : "Masukkan quantity"
                                        }
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        {data.type === "correction"
                                            ? "Stok akan diubah menjadi nilai ini"
                                            : data.type === "adjustment_in" || data.type === "return"
                                            ? "Stok akan ditambah sebanyak ini"
                                            : data.type === "adjustment_out" ||
                                              data.type === "damage"
                                            ? "Stok akan dikurangi sebanyak ini"
                                            : "Stok akan disesuaikan"}
                                    </p>
                                    {errors.quantity && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.quantity}
                                        </p>
                                    )}
                                </div>

                                {/* Reason */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Alasan
                                    </label>
                                    <input
                                        type="text"
                                        value={data.reason}
                                        onChange={(e) =>
                                            setData("reason", e.target.value)
                                        }
                                        placeholder="Alasan adjustment"
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    {errors.reason && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.reason}
                                        </p>
                                    )}
                                </div>

                                {/* Notes */}
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Catatan
                                    </label>
                                    <textarea
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData("notes", e.target.value)
                                        }
                                        placeholder="Catatan tambahan (opsional)"
                                        rows={3}
                                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    />
                                    {errors.notes && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.notes}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Submit */}
                            <div className="flex justify-end gap-2">
                                <Link href={route("inventory-adjustments.index")}>
                                    <Button variant="secondary" type="button" label="Batal" />
                                </Link>
                                <Button
                                    variant="primary"
                                    type="submit"
                                    disabled={processing}
                                    icon={<IconDeviceFloppy className="w-4 h-4" />}
                                    label={processing ? "Menyimpan..." : "Simpan"}
                                />
                            </div>
                        </div>

                        {/* Sidebar - Product Info */}
                        <div>
                            {selectedProduct ? (
                                <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                                    <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                        <IconPackage className="w-5 h-5 text-blue-500" />
                                        Info Produk
                                    </h2>
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Nama Produk
                                            </p>
                                            <p className="font-medium text-slate-800 dark:text-white">
                                                {selectedProduct.title}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Barcode
                                            </p>
                                            <p className="font-medium text-slate-800 dark:text-white">
                                                {selectedProduct.barcode || "-"}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Stok Saat Ini
                                            </p>
                                            <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                                {selectedProduct.stock?.toLocaleString(
                                                    "id-ID"
                                                ) || 0}
                                            </p>
                                        </div>
                                        {selectedProduct.inventory && (
                                            <div>
                                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                                    Stok di Inventory
                                                </p>
                                                <p className="font-medium text-slate-800 dark:text-white">
                                                    {selectedProduct.inventory.quantity?.toLocaleString(
                                                        "id-ID"
                                                    ) || 0}
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Preview */}
                                    {data.quantity && (
                                        <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                                            <p className="text-sm text-slate-500 dark:text-slate-400 mb-2">
                                                Preview Perubahan
                                            </p>
                                            <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3">
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-slate-600 dark:text-slate-400">
                                                        Sebelum
                                                    </span>
                                                    <span className="font-medium text-slate-800 dark:text-white">
                                                        {selectedProduct.stock?.toLocaleString(
                                                            "id-ID"
                                                        ) || 0}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between text-sm mt-1">
                                                    <span className="text-slate-600 dark:text-slate-400">
                                                        Perubahan
                                                    </span>
                                                    <span
                                                        className={
                                                            data.type ===
                                                                "correction"
                                                                ? "text-blue-600 dark:text-blue-400"
                                                                : data.type ===
                                                                      "adjustment_in" || data.type === "return"
                                                                ? "text-green-600 dark:text-green-400"
                                                                : "text-red-600 dark:text-red-400"
                                                        }
                                                    >
                                                        {data.type === "correction"
                                                            ? `→ ${parseFloat(
                                                                  data.quantity
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )}`
                                                            : data.type === "adjustment_in" || data.type === "return"
                                                            ? `+${parseFloat(
                                                                  data.quantity
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )}`
                                                            : `-${parseFloat(
                                                                  data.quantity
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )}`}
                                                    </span>
                                                </div>
                                                <hr className="my-2 border-slate-200 dark:border-slate-700" />
                                                <div className="flex items-center justify-between">
                                                    <span className="text-slate-600 dark:text-slate-400">
                                                        Sesudah
                                                    </span>
                                                    <span className="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                        {data.type === "correction"
                                                            ? parseFloat(
                                                                  data.quantity
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )
                                                            : data.type === "adjustment_in" || data.type === "return"
                                                            ? (
                                                                  (selectedProduct.stock ||
                                                                      0) +
                                                                  parseFloat(
                                                                      data.quantity
                                                                  )
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )
                                                            : Math.max(
                                                                  0,
                                                                  (selectedProduct.stock ||
                                                                      0) -
                                                                      parseFloat(
                                                                          data.quantity
                                                                      )
                                                              ).toLocaleString(
                                                                  "id-ID"
                                                              )}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                                    <div className="text-center text-slate-500 dark:text-slate-400">
                                        <IconPackage className="w-12 h-12 mx-auto mb-2 opacity-50" />
                                        <p>Pilih produk untuk melihat informasi</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}
