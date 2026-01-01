import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head } from '@inertiajs/react';
import Button from '@/Components/Dashboard/Button';
import { IconDatabaseOff } from '@tabler/icons-react';
import Search from '@/Components/Dashboard/Search';
import Table from '@/Components/Dashboard/Table';
import Pagination from '@/Components/Dashboard/Pagination';
import Input from '@/Components/Dashboard/Input';

export default function Index({ purchases }) {
    return (
        <>
            <Head title="Pembelian" />

            <div className="mb-2">
                <div className="flex justify-between items-center gap-2">
                    <Button />
                    <div className="w-full md:w-4/12">
                        <Search
                            url={route('purchase.index')}
                            placeholder="Cari data berdasarkan nomor invoice..."
                        />
                    </div>
                </div>
            </div>

            <Table.Card title="Data Pembelian">
               <Table>
                <Table.Thead>
                    <tr>
                        <Table.Th className="w-10 text-center">No</Table.Th>
                        <Table.Th className="text-center">Supplier</Table.Th>
                        <Table.Th className="text-center">PPN</Table.Th>
                        <Table.Th className="text-center">Tanggal</Table.Th>
                        <Table.Th className="text-center">Reference</Table.Th>

                        <Table.Th className="text-center">Barcode</Table.Th>
                        <Table.Th className="text-right">Quantity</Table.Th>
                        <Table.Th className="text-center">Gudang</Table.Th>
                        <Table.Th className="text-center">Batch</Table.Th>
                        <Table.Th className="text-center">Kadaluarsa</Table.Th>
                        <Table.Th className="text-center">Mata Uang</Table.Th>
                        <Table.Th className="text-right">Harga</Table.Th>
                        <Table.Th className="text-right">Diskon (%)</Table.Th>
                        <Table.Th className="text-right">PPN (%)</Table.Th>
                        <Table.Th className="text-right">Total</Table.Th>

                        <Table.Th className="text-center">Status</Table.Th>
                        <Table.Th className="text-center">Catatan</Table.Th>
                    </tr>
                    </Table.Thead>


                <Table.Tbody>
                    {purchases?.data?.length ? (
                        purchases.data.map((purchase, i) =>
                        purchase.items?.length ? (
                            purchase.items.map((item, idx) => (
                            <tr
                                key={`${purchase.id}-${idx}`}
                                className="hover:bg-gray-100 dark:hover:bg-gray-900"
                            >
                                {/* No */}
                                <Table.Td className="text-center">
                                {i + 1 + (purchases.current_page - 1) * purchases.per_page}
                                </Table.Td>

                                {/* Purchase level */}
                                <Table.Td className="text-center">
                                {purchase.supplier_name ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {purchase.tax_included ? 'Ya' : 'Tidak'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {purchase.purchase_date ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {purchase.reference ?? '-'}
                                </Table.Td>

                                {/* Item level */}
                                <Table.Td className="text-center">
                                {item.barcode ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-right">
                                {item.quantity ?? 0}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {item.warehouse ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {item.batch ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {item.expired ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {item.currency ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-right">
                                {item.purchase_price?.toLocaleString('id-ID') ?? 0}
                                </Table.Td>

                                <Table.Td className="text-right">
                                {item.discount_percent ?? 0}%
                                </Table.Td>

                                <Table.Td className="text-right">
                                {item.tax_percent ?? 0}%
                                </Table.Td>

                                <Table.Td className="text-right">
                                {item.total_price?.toLocaleString('id-ID') ?? 0}
                                </Table.Td>

                                {/* Status & Notes */}
                                <Table.Td className="text-center">
                                {purchase.status ?? '-'}
                                </Table.Td>

                                <Table.Td className="text-center">
                                {purchase.notes ?? '-'}
                                </Table.Td>
                            </tr>
                            ))
                        ) : (
                            <tr key={purchase.id}>
                            <Table.Td colSpan={17} className="text-center text-gray-400">
                                Tidak ada item
                            </Table.Td>
                            </tr>
                        )
                        )
                    ) : (
                        <tr>
                        <Table.Td colSpan={17} className="text-center py-8">
                            <IconDatabaseOff size={48} className="mx-auto text-gray-400" />
                            <p className="text-gray-500 mt-2">Tidak ada data pembelian</p>
                        </Table.Td>
                        </tr>
                    )}
                    </Table.Tbody>


            </Table>


                <Pagination meta={purchases} />
            </Table.Card>
        </>
    );
}

Index.layout = page => <DashboardLayout>{page}</DashboardLayout>;
Index.permissions = ['purchase-access'];
Index.roles = ['Admin', 'Manager'];
