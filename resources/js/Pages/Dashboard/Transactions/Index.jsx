import React, { useEffect, useMemo, useState, useCallback, useRef } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import axios from "axios";
import toast from "react-hot-toast";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Card from "@/Components/Dashboard/Card";
import Input from "@/Components/Dashboard/Input";
import Button from "@/Components/Dashboard/Button";
import InputSelect from "@/Components/Dashboard/InputSelect";
import Table from "@/Components/Dashboard/Table";
import {
    IconArrowRight,
    IconBarcode,
    IconCash,
    IconCreditCard,
    IconReceipt,
    IconShoppingBag,
    IconShoppingCartPlus,
    IconTrash,
    IconUser,
    IconSearch,
    IconX,
} from "@tabler/icons-react";

export default function Index({
    carts = [],
    carts_total = 0,
    customers = [],
    paymentGateways = [],
    defaultPaymentGateway = "cash",
}) {
    const { auth, errors } = usePage().props;

    const [barcode, setBarcode] = useState("");
    const [product, setProduct] = useState(null);
    const [quantity, setquantity] = useState(1);
    const [discountInput, setDiscountInput] = useState("");
    const [cashInput, setCashInput] = useState("");
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState(
        defaultPaymentGateway ?? "cash"
    );

    // New states for suggestions dropdown
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [isFetchingSuggestions, setIsFetchingSuggestions] = useState(false);
    const suggestionsRef = useRef(null);
    const inputRef = useRef(null);
    const debounceTimerRef = useRef(null);

    const [showProductModal, setShowProductModal] = useState(false);

    const handleOpenProductModal = () => {
    if (isFetchingSuggestions) return; // lock saat loading
        setShowProductModal(true);
    };

    const handleCloseProductModal = () => {
        setShowProductModal(false);
    };


    useEffect(() => {
        setPaymentMethod(defaultPaymentGateway ?? "cash");
    }, [defaultPaymentGateway]);

    const discount = useMemo(
        () => Math.max(0, Number(discountInput) || 0),
        [discountInput]
    );
    const subtotal = useMemo(() => carts_total ?? 0, [carts_total]);
    const payable = useMemo(
        () => Math.max(subtotal - discount, 0),
        [subtotal, discount]
    );
    const cash = useMemo(
        () =>
            paymentMethod === "cash"
                ? Math.max(0, Number(cashInput) || 0)
                : payable,
        [cashInput, paymentMethod, payable]
    );
    const change = useMemo(() => Math.max(cash - payable, 0), [cash, payable]);
    const remaining = useMemo(
        () => Math.max(payable - cash, 0),
        [payable, cash]
    );
    const cartCount = useMemo(
        () => carts.reduce((total, item) => total + Number(item.quantity), 0),
        [carts]
    );

    const paymentOptions = useMemo(() => {
        const options = Array.isArray(paymentGateways)
            ? paymentGateways.filter(
                  (gateway) =>
                      gateway?.value && gateway.value.toLowerCase() !== "cash"
              )
            : [];

        return [
            {
                value: "cash",
                label: "Tunai",
                description: "Pembayaran tunai langsung di kasir.",
            },
            ...options,
        ];
    }, [paymentGateways]);

    const activePaymentOption =
        paymentOptions.find((option) => option.value === paymentMethod) ??
        paymentOptions[0];

    const isCashPayment = activePaymentOption?.value === "cash";

    useEffect(() => {
        if (
            paymentOptions.length &&
            !paymentOptions.find((option) => option.value === paymentMethod)
        ) {
            setPaymentMethod(paymentOptions[0].value);
        }
    }, [paymentOptions, paymentMethod]);

    useEffect(() => {
        if (!isCashPayment && payable >= 0) {
            setCashInput(String(payable));
        }
    }, [isCashPayment, payable]);

    const submitLabel = isCashPayment
        ? remaining > 0
            ? "Menunggu Pembayaran"
            : "Selesaikan Transaksi"
        : `Buat Pembayaran ${activePaymentOption?.label ?? ""}`;

    const isSubmitDisabled =
        carts.length === 0 || (isCashPayment && remaining > 0);

    const formatPrice = (value = 0) =>
        value.toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        });

    const sanitizeNumericInput = (value) => {
        const numbersOnly = value.replace(/[^\d]/g, "");

        if (numbersOnly === "") return "";

        return numbersOnly.replace(/^0+(?=\d)/, "");
    };

    const resetProductForm = () => {
        setBarcode("");
        setProduct(null);
        setquantity(1);
        setSuggestions([]);
        setShowSuggestions(false);
    };

    // Debounced product suggestions fetch
    const fetchSuggestions = useCallback(async (query) => {
        if (query.length < 2) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        setIsFetchingSuggestions(true);

        try {
            const { data } = await axios.get(
                "/dashboard/transactions/suggestProducts",
                { params: { query } }
            );

            if (data.success && data.data.length > 0) {
                setSuggestions(data.data);
                setShowSuggestions(true);
            } else {
                setSuggestions([]);
                setShowSuggestions(false);
            }
        } catch (error) {
            console.error("Error fetching suggestions:", error);
            setSuggestions([]);
        } finally {
            setIsFetchingSuggestions(false);
        }
    }, []);

    // Handle barcode input change with debounce
    const handleBarcodeChange = (event) => {
        const value = event.target.value;
        setBarcode(value);

        // Clear previous debounce timer
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        // Set new debounce timer (300ms delay)
        debounceTimerRef.current = setTimeout(() => {
            fetchSuggestions(value);
        }, 300);
    };

    // Handle selecting a suggestion
    const handleSelectSuggestion = (selectedProduct) => {
        setBarcode(selectedProduct.barcode);
        setProduct(selectedProduct);
        setquantity(1);
        setSuggestions([]);
        setShowSuggestions(false);
    };

    // Close suggestions when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (
                suggestionsRef.current &&
                !suggestionsRef.current.contains(event.target) &&
                inputRef.current &&
                !inputRef.current.contains(event.target)
            ) {
                setShowSuggestions(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, []);

    // Cleanup debounce timer on unmount
    useEffect(() => {
        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    const handleSearchProduct = async (event) => {
        event?.preventDefault();

        if (!barcode.trim()) {
            setProduct(null);
            toast.error("Masukkan barcode terlebih dahulu");
            return;
        }

        setIsSearching(true);
        setShowSuggestions(false);

        try {
            const { data } = await axios.post(
                "/dashboard/transactions/searchProduct",
                { barcode }
            );

            if (data.success) {
                setProduct(data.data);
                setquantity(1);
            } else {
                setProduct(null);
                toast.error("Produk tidak ditemukan");
            }
        } catch (error) {
            console.error(error);
            toast.error("Gagal mencari produk, coba lagi");
        } finally {
            setIsSearching(false);
        }
    };

    const handleAddToCart = (event) => {
        event.preventDefault();

        if (!product?.id) {
            toast.error("Silakan scan produk terlebih dahulu");
            return;
        }

        if (quantity < 1) {
            toast.error("Jumlah minimal 1");
            return;
        }

        if (product?.stock && quantity > product.stock) {
            toast.error("Jumlah melebihi stok tersedia");
            return;
        }

        router.post(
            route("transactions.addToCart"),
            {
                barcode: product.barcode,
                sell_price: product.sell_price,
                quantity,
            },
            {
                onSuccess: () => {
                    toast.success("Produk ditambahkan ke keranjang");
                    resetProductForm();
                },
            }
        );
    };

    const handleSubmitTransaction = (event) => {
        event.preventDefault();

        if (carts.length === 0) {
            toast.error("Keranjang masih kosong");
            return;
        }

        if (!selectedCustomer?.id) {
            toast.error("Pilih pelanggan terlebih dahulu");
            return;
        }

        if (isCashPayment && cash < payable) {
            toast.error("Jumlah pembayaran kurang dari total");
            return;
        }

        router.post(
            route("transactions.store"),
            {
                customer_id: selectedCustomer.id,
                discount,
                grand_total: payable,
                cash: isCashPayment ? cash : payable,
                change: isCashPayment ? change : 0,
                payment_gateway: isCashPayment ? null : paymentMethod,
            },
            {
                onSuccess: () => {
                    setDiscountInput("");
                    setCashInput("");
                    setSelectedCustomer(null);
                    setPaymentMethod(defaultPaymentGateway ?? "cash");
                    toast.success("Transaksi berhasil disimpan");
                },
            }
        );
    };

    const quantityDisabled = !product?.id;

    return (
        <>
            <Head title="Transaksi" />

            <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card
                        title="Total Item"
                        icon={<IconShoppingBag size={18} />}
                    >
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {cartCount}
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Produk di keranjang
                        </p>
                    </Card>
                    <Card title="Subtotal" icon={<IconReceipt size={18} />}>
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {formatPrice(subtotal)}
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Belanja sebelum diskon
                        </p>
                    </Card>
                    <Card title="Kembalian" icon={<IconCash size={18} />}>
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {formatPrice(change)}
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {remaining > 0
                                ? `Kurang ${formatPrice(remaining)}`
                                : "Siap diberikan ke pelanggan"}
                        </p>
                    </Card>
                </div>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="space-y-5 xl:col-span-2">
                        <Card
                            title="Scan / Cari Produk"
                            icon={<IconBarcode size={20} />}
                            footer={
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        {product
                                            ? `Stok tersedia: ${product.stock}`
                                            : "Scan barcode atau ketik manual"}
                                    </p>
                                    <Button
                                        type="submit"
                                        label="Tambahkan ke Keranjang"
                                        icon={<IconShoppingCartPlus size={18} />}
                                        disabled={quantityDisabled}
                                        variant={quantityDisabled ? "secondary" : "primary"}
                                    />
                                </div>
                            }
                            form={handleAddToCart}
                        >
                            <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
                                <div className="space-y-3">
                                    {/* Barcode input with autocomplete dropdown */}
                                    <div className="relative" ref={inputRef}>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">
                                            Scan / Input Barcode
                                        </label>
                                        <div className="relative">
                                            <input
                                                type="text"
                                                placeholder="Masukkan barcode atau nama produk"
                                                value={barcode}
                                                disabled={isSearching}
                                                onChange={handleBarcodeChange}
                                                onKeyDown={(event) => {
                                                    if (event.key === "Enter") {
                                                        event.preventDefault();
                                                        setShowSuggestions(false);
                                                        handleSearchProduct(event);
                                                    } else if (event.key === "Escape") {
                                                        setShowSuggestions(false);
                                                    }
                                                }}
                                                onFocus={() => {
                                                    if (suggestions.length > 0) {
                                                        setShowSuggestions(true);
                                                    }
                                                }}
                                                className="w-full px-3 py-2 pr-10 text-sm rounded-lg border transition-colors
                                                    bg-white text-gray-900 border-gray-200 placeholder-gray-400
                                                    focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                                    dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700 dark:placeholder-gray-500
                                                    disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-800 dark:disabled:text-gray-400"
                                            />
                                            <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                                {isFetchingSuggestions ? (
                                                    <svg className="animate-spin h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                ) : barcode ? (
                                                    <button
                                                    type="button"
                                                    onClick={handleOpenProductModal}
                                                    className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                                >
                                                    <IconSearch size={16} />
                                                </button>
                                                ) : (
                                                    <IconSearch size={16} className="text-gray-400 dark:text-gray-500" />
                                                )}
                                            </div>
                                        </div>

                                        {/* Suggestions dropdown */}
                                        {showSuggestions && suggestions.length > 0 && (
                                            <div
                                                ref={suggestionsRef}
                                                className="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-64 overflow-y-auto"
                                            >
                                                {suggestions.map((item) => (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => handleSelectSuggestion(item)}
                                                        className="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-b-0 transition-colors"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <p className="font-medium text-gray-900 dark:text-white text-sm">
                                                                    {item.title}
                                                                </p>
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    Barcode: {item.barcode}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-indigo-600 dark:text-indigo-400 text-sm">
                                                                    {formatPrice(item.sell_price)}
                                                                </p>
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    Stok: {item.stock}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex gap-3">
                                        <Button
                                            type="button"
                                            onClick={handleSearchProduct}
                                            label={isSearching ? 'Mencari...' : 'Cari Produk'}
                                            icon={<IconSearch size={16} />}
                                            variant="primary"
                                            disabled={isSearching}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={resetProductForm}
                                            label="Reset"
                                        />
                                    </div>
                                </div>
                                <div className="rounded-lg border border-dashed border-gray-200 p-4 dark:border-gray-700">
                                    {product ? (
                                        <div className="space-y-2">
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Produk terpilih
                                            </p>
                                            <h4 className="text-lg font-semibold text-gray-900 dark:text-white">
                                                {product.title}
                                            </h4>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Harga jual
                                            </p>
                                            <p className="text-xl font-semibold text-indigo-500 dark:text-indigo-400">
                                                {formatPrice(
                                                    product.sell_price
                                                )}
                                            </p>
                                            <div className="mt-4 space-y-2">
                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    Jumlah
                                                </label>
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        className="px-3 py-2 border bg-white text-gray-700 hover:bg-gray-100 rounded-md text-lg font-semibold dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                                        disabled={
                                                            quantityDisabled ||
                                                            quantity <= 1
                                                        }
                                                        onClick={() =>
                                                            setquantity((prev) =>
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        prev
                                                                    ) - 1
                                                                )
                                                            )
                                                        }
                                                    >
                                                        -
                                                    </button>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        className="w-full px-3 py-1.5 border text-sm rounded-md text-center focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800"
                                                        value={quantity}
                                                        disabled={quantityDisabled}
                                                        onChange={(event) =>
                                                            setquantity(
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value
                                                                    ) || 1
                                                                )
                                                            )
                                                        }
                                                    />
                                                    <button
                                                        type="button"
                                                        className="px-3 py-2 border bg-white text-gray-700 hover:bg-gray-100 rounded-md text-lg font-semibold dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                                        disabled={quantityDisabled}
                                                        onClick={() =>
                                                            setquantity(
                                                                (prev) =>
                                                                    Number(
                                                                        prev
                                                                    ) + 1
                                                            )
                                                        }
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex h-full flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400">
                                            <IconShoppingBag
                                                size={36}
                                                className="mb-2"
                                            />
                                            <p className="text-sm">
                                                Belum ada produk yang dipilih
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </Card>

                        <Table.Card title="Keranjang">
                            <Table>
                                <Table.Thead>
                                    <tr>
                                        <Table.Th className="w-16 text-center">
                                            No
                                        </Table.Th>
                                        <Table.Th>Produk</Table.Th>
                                        <Table.Th className="text-right">
                                            Harga
                                        </Table.Th>
                                        <Table.Th className="text-center">
                                            quantity
                                        </Table.Th>
                                        <Table.Th className="text-right">
                                            Subtotal
                                        </Table.Th>
                                        <Table.Th></Table.Th>
                                    </tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {carts.length === 0 && (
                                        <tr>
                                            <Table.Td
                                                colSpan={6}
                                                className="py-6 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                Keranjang masih kosong
                                            </Table.Td>
                                        </tr>
                                    )}

                                    {carts.map((item, index) => (
                                        <tr
                                            key={`${item.id}-${item.barcode}`}
                                        >
                                            <Table.Td className="text-center">
                                                {index + 1}
                                            </Table.Td>
                                            <Table.Td>
                                                <p className="font-semibold text-gray-900 dark:text-white">
                                                    {item.product.title}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    SKU: {item.product.barcode}
                                                </p>
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatPrice(item.price)}
                                            </Table.Td>
                                            <Table.Td className="text-center">
                                                {item.quantity}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatPrice(
                                                    item.price * item.quantity
                                                )}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                <Button
                                                    type="delete"
                                                    icon={
                                                        <IconTrash size={16} />
                                                    }
                                                    url={route(
                                                        "transactions.destroyCart",
                                                        item.id
                                                    )}
                                                    variant="danger"
                                                />
                                            </Table.Td>
                                        </tr>
                                    ))}
                                </Table.Tbody>
                                <tfoot>
                                    <tr>
                                        <Table.Td
                                            colSpan={4}
                                            className="text-right font-semibold"
                                        >
                                            Total
                                        </Table.Td>
                                        <Table.Td className="text-right font-semibold">
                                            {formatPrice(subtotal)}
                                        </Table.Td>
                                        <Table.Td></Table.Td>
                                    </tr>
                                </tfoot>
                            </Table>
                        </Table.Card>
                    </div>

                    <div className="space-y-5">
                        <Card
                            title="Informasi Pelanggan"
                            icon={<IconUser size={18} />}
                        >
                            <div className="space-y-3">
                                <Input
                                    label="Kasir"
                                    type="text"
                                    value={auth.user.name}
                                    disabled
                                />

                                <InputSelect
                                    label="Pelanggan"
                                    data={customers}
                                    selected={selectedCustomer}
                                    setSelected={setSelectedCustomer}
                                    placeholder="Cari nama pelanggan"
                                    errors={errors?.customer_id}
                                    multiple={false}
                                    searchable
                                    displayKey="name"
                                />
                            </div>
                        </Card>

                        <Card
                            title="Ringkasan Pembayaran"
                            icon={<IconReceipt size={18} />}
                        >
                            <div className="space-y-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Subtotal
                                    </span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {formatPrice(subtotal)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Diskon
                                    </span>
                                    <span className="font-medium text-rose-500 dark:text-rose-400">
                                        - {formatPrice(discount)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-base font-semibold">
                                    <span className="text-gray-900 dark:text-white">Total Bayar</span>
                                    <span className="text-gray-900 dark:text-white">{formatPrice(payable)}</span>
                                </div>

                                <div className="grid gap-3">
                                    <Input
                                        type="text"
                                        inputMode="numeric"
                                        label="Diskon (Rp)"
                                        placeholder="0"
                                        value={discountInput}
                                        onChange={(event) =>
                                            setDiscountInput(
                                                sanitizeNumericInput(
                                                    event.target.value
                                                )
                                            )
                                        }
                                    />
                                    <Input
                                        type="text"
                                        inputMode="numeric"
                                        label={
                                            isCashPayment
                                                ? "Bayar Tunai (Rp)"
                                                : "Nominal Pembayaran"
                                        }
                                        placeholder="0"
                                        value={
                                            isCashPayment
                                                ? cashInput
                                                : payable.toString()
                                        }
                                        disabled={!isCashPayment}
                                        readOnly={!isCashPayment}
                                        onChange={(event) =>
                                            setCashInput(
                                                sanitizeNumericInput(
                                                    event.target.value
                                                )
                                            )
                                        }
                                    />
                                    {!isCashPayment && (
                                        <p className="text-xs text-amber-600 dark:text-amber-500">
                                            Nominal mengikuti total tagihan
                                            saat membuat tautan pembayaran.
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <p className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Pilih Metode Pembayaran
                                    </p>
                                    <div className="grid gap-3">
                                        {paymentOptions.map((option) => {
                                            const isActive =
                                                option.value === paymentMethod;
                                            const IconComponent =
                                                option.value === "cash"
                                                    ? IconCash
                                                    : IconCreditCard;

                                            return (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() =>
                                                        setPaymentMethod(
                                                            option.value
                                                        )
                                                    }
                                                    className={`w-full rounded-xl border p-3 text-left transition ${
                                                        isActive
                                                            ? "border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10"
                                                            : "border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700"
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between gap-3">
                                                        <div>
                                                            <p className="font-semibold text-gray-900 dark:text-white">
                                                                {option.label}
                                                            </p>
                                                            {option?.description && (
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        option.description
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <IconComponent
                                                            size={18}
                                                            className={
                                                                isActive
                                                                    ? "text-indigo-600 dark:text-indigo-400"
                                                                    : "text-gray-400 dark:text-gray-500"
                                                            }
                                                        />
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {!isCashPayment && (
                                        <p className="text-xs text-amber-600 dark:text-amber-500">
                                            Tautan pembayaran akan muncul di
                                            halaman invoice setelah transaksi
                                            dibuat.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-500 dark:text-gray-400">
                                            Metode
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {activePaymentOption?.label ??
                                                "Tunai"}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-sm">
                                        <span className="text-gray-500 dark:text-gray-400">
                                            {isCashPayment ? "Kembalian" : "Status"}
                                        </span>
                                        <span
                                            className={`font-semibold ${
                                                isCashPayment
                                                    ? "text-emerald-500 dark:text-emerald-400"
                                                    : "text-amber-500 dark:text-amber-400"
                                            }`}
                                        >
                                            {isCashPayment
                                                ? change > 0
                                                    ? formatPrice(change)
                                                    : "-"
                                                : "Menunggu pembayaran"}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    label={submitLabel}
                                    icon={<IconArrowRight size={18} />}
                                    onClick={handleSubmitTransaction}
                                    disabled={isSubmitDisabled}
                                    variant={isSubmitDisabled ? "secondary" : "primary"}
                                    className="w-full"
                                />
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
    
}

Index.layout = (page) => <DashboardLayout children={page} />;
