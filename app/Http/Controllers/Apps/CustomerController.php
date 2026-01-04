<?php

namespace App\Http\Controllers\Apps;

use Inertia\Inertia;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // Get per_page from request, default to 10
        $perPage = $request->input('per_page', 10);

        // Validate per_page value
        $perPage = in_array($perPage, [5, 10, 25, 50, 100]) ? $perPage : 10;

        // Get customers with search filter
        $customers = Customer::when($request->search, function ($query, $search) {
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('no_telp', 'like', '%' . $search . '%')
                ->orWhere('address', 'like', '%' . $search . '%');
        })->latest()->paginate($perPage)->withQueryString();

        // Return inertia with filters
        return Inertia::render('Dashboard/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->search ?? '',
                'per_page' => (string) $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return Inertia::render('Dashboard/Customers/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        /**
         * validate
         */
        $request->validate([
            'name' => 'required',
            'no_telp' => 'required|unique:customers',
            'address' => 'required',
        ]);

        //create customer
        Customer::create([
            'name' => $request->name,
            'no_telp' => $request->no_telp,
            'address' => $request->address,
        ]);

        //redirect
        return to_route('customers.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Customer $customer)
    {
        return Inertia::render('Dashboard/Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Customer $customer)
    {
        /**
         * validate
         */
        $request->validate([
            'name' => 'required',
            'no_telp' => 'required|unique:customers,no_telp,' . $customer->id,
            'address' => 'required',
        ]);

        //update customer
        $customer->update([
            'name' => $request->name,
            'no_telp' => $request->no_telp,
            'address' => $request->address,
        ]);

        //redirect
        return to_route('customers.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);

        if ($customer->transactions()->exists()) {
            throw ValidationException::withMessages([
                'error' => 'Customer tidak bisa dihapus karena memiliki transaksi.'
            ]);
        }

        $customer->delete();

        return back()->with('success', 'Customer berhasil dihapus');
    }
}
