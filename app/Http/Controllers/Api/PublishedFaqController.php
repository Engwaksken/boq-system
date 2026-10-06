<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;

class PublishedFaqController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($data['search'] ?? '');

        return FaqResource::collection(Faq::where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('question', 'like', '%'.$search.'%')->orWhere('answer', 'like', '%'.$search.'%')))
            ->orderBy('sort_order')->orderBy('id')->paginate(20));
    }
}
