<?php

namespace App\Http\Controllers;

use App\Support\Signature;
use Illuminate\Http\Request;

/** Administration > Signature et cachet: the image printed at the bottom of every document. */
class SignatureController extends Controller
{
    public function edit()
    {
        return view('settings.signature', [
            'image' => Signature::dataUri(),
            'signatory' => Signature::signatory(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'signature' => [Signature::exists() ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'signatory' => ['nullable', 'string', 'max:120'],
        ], [
            'signature.required' => 'Choisissez une image (PNG ou JPG).',
            'signature.mimes' => 'L\'image doit etre un fichier PNG ou JPG.',
            'signature.max' => 'L\'image ne doit pas depasser 2 Mo.',
        ]);

        if ($request->hasFile('signature')) {
            Signature::store($request->file('signature'), $data['signatory'] ?? null);
        } else {
            Signature::saveSignatory($data['signatory'] ?? null);
        }

        return redirect()->route('settings.signature.edit')->with('success', 'Signature enregistree : elle apparait en bas des documents.');
    }

    public function destroy()
    {
        Signature::delete();

        return redirect()->route('settings.signature.edit')->with('success', 'Signature supprimee : les documents n\'en portent plus.');
    }
}
