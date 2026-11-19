<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('menus')->orderBy('name', 'asc')->get();
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q),
            ],
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'status' => true,
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil ditambahkan!');
    }

    public function update(Request $request, Category $category)
    {
        $tenantId = TenantContext::id();

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q)
                    ->ignore($category->id),
            ],
            'status' => ['sometimes', 'boolean'],
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'status' => $request->has('status'),
        ]);

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui!');
    }

    public function destroy(Category $category)
    {
        if ($category->menus()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh beberapa menu.');
        }

        $category->forceDelete();

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus secara permanen.');
    }
}
