<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    use RedirectsToList;

    public function index()
    {
        $services = Service::query()
            ->search(request('q'))
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('erp.services.index', compact('services'));
    }

    public function create()
    {
        $service = new Service();

        return view('erp.services.create', compact('service'));
    }

    public function store(StoreServiceRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $service = Service::create($data);

        return $this->createdRedirect('services', $service, 'Service cree avec succes.');
    }

    public function show(Service $service)
    {
        return view('erp.services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        return view('erp.services.edit', compact('service'));
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['updated_by'] = Auth::id();

        $service->update($data);

        return redirect()->route('erp.services.index')->with('success', 'Service mis a jour avec succes.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('erp.services.index')->with('success', 'Service supprime avec succes.');
    }
}
