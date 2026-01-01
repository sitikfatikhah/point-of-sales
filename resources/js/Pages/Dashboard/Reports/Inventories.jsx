import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { IconDatabaseOff } from '@tabler/icons-react';
import Input from '@/Components/Dashboard/Input';
import Table from '@/Components/Dashboard/Table';
import Pagination from '@/Components/Dashboard/Pagination';
import Button from '@/Components/Dashboard/Button';

export default function Inventories({ purchase, sale, summary, filters, products }) {
  const [barcode, setBarcode] = useState(filters?.barcode ?? '');
  const [description, setDescription] = useState(filters?.description ?? '');
  const [categoryId, setCategoryId] = useState(filters?.category_id ?? '');

  const handleFilterChange = (event, filterType) => {
    const value = event.target.value;
    if (filterType === 'barcode') setBarcode(value);
    if (filterType === 'description') setDescription(value);
    if (filterType === 'category_id') setCategoryId(value);
  };

  const handleFilterSubmit = (event) => {
    event.preventDefault();
    router.get(
      '/dashboard/reports/inventories',
      { barcode, description, category_id: categoryId },
      { preserveState: true }
    );
  };

  return (
    <>
      <Head title="Laporan Persediaan" />

      {/* Filter Form */}
      <div className="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <form onSubmit={handleFilterSubmit} className="flex flex-wrap gap-2 md:gap-4">
          <Input
            placeholder="Barcode"
            value={barcode}
            onChange={(e) => handleFilterChange(e, 'barcode')}
          />
          <Input
            placeholder="Description"
            value={description}
            onChange={(e) => handleFilterChange(e, 'description')}
          />
          <select
            value={categoryId}
            onChange={(e) => handleFilterChange(e, 'category_id')}
            className="px-4 py-2 border rounded"
          >
            <option value="">All Categories</option>
            {products.map((product) => (
              <option key={product.id} value={product.category_id || ''}>
                {product.category?.name || 'N/A'}
              </option>
            ))}
          </select>
          <Button type="submit">Apply Filters</Button>
        </form>
      </div>

      {/* Inventory Summary */}
      <div className="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="p-4 bg-gray-800 dark:bg-gray-800 rounded shadow">
          <h2 className="text-lg font-semibold">Total Purchase</h2>
          <p>Quantity: {summary.purchase_quantity}</p>
          <p>Total Purchase Price: ${summary.purchase_price?.toLocaleString() ?? 0}</p>
        </div>
        <div className="p-4 bg-gray-800 dark:bg-gray-800 rounded shadow">
          <h2 className="text-lg font-semibold">Total Sale</h2>
          <p>Quantity: {summary.sale_quantity}</p>
          <p>Total Sale Price: ${summary.grand_total?.toLocaleString() ?? 0}</p>
        </div>
        <div className="p-4 bg-gray-800 dark:bg-gray-800 rounded shadow">
          <h2 className="text-lg font-semibold">Purchase Balance</h2>
          <p>{purchase}</p>
        </div>
        <div className="p-4 bg-gray-800 dark:bg-gray-800 rounded shadow">
          <h2 className="text-lg font-semibold">Sale Balance</h2>
          <p>{sale}</p>
        </div>
      </div>

      {/* Inventory Table */}
      <Table.Card title="Detail Inventory">
        <Table>
          <Table.Thead>
            <tr>
              <Table.Th className="text-center">No</Table.Th>
              <Table.Th>Barcode</Table.Th>
              <Table.Th>Description</Table.Th>
              <Table.Th>Category</Table.Th>
              <Table.Th className="text-right">Quantity</Table.Th>
              <Table.Th className="text-right">Total Price</Table.Th>
            </tr>
          </Table.Thead>

          <Table.Tbody>
            {products?.length ? (
              products.map((product, index) => (
                <tr key={product.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                  <Table.Td className="text-center">{index + 1}</Table.Td>
                  <Table.Td>{product.barcode ?? '-'}</Table.Td>
                  <Table.Td>{product.description ?? '-'}</Table.Td>
                  <Table.Td>{product.category?.name ?? 'N/A'}</Table.Td>
                  <Table.Td className="text-right">{product.quantity ?? 0}</Table.Td>
                  <Table.Td className="text-right">
                    ${product.total_price?.toLocaleString() ?? 0}
                  </Table.Td>
                </tr>
              ))
            ) : (
              <tr>
                <Table.Td colSpan={6} className="text-center py-8 text-gray-400">
                  <IconDatabaseOff size={48} className="mx-auto" />
                  <p className="mt-2">Tidak ada data inventory</p>
                </Table.Td>
              </tr>
            )}
          </Table.Tbody>
        </Table>

        {/* Pagination placeholder */}
        <Pagination meta={{}} />
      </Table.Card>
    </>
  );
}

Inventories.layout = (page) => <DashboardLayout>{page}</DashboardLayout>;
