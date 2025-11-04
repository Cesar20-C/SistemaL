<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificadoController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim((string)$request->get('q'));        // id
        $producto  = trim((string)$request->get('producto')); // producto
        $batch     = $request->get('batch');                  // número exacto
        $f_from    = $request->get('f_from');                 // fecha elaboración desde
        $f_to      = $request->get('f_to');                   // fecha elaboración hasta
        $pdf       = $request->get('pdf');                    // '', '1'=con PDF, '0'=sin PDF

        $certs = Certificado::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('producto', 'like', "%{$q}%");
                    if (ctype_digit($q)) {
                        $n = (int) $q;
                        $qq->orWhere('numero_batch', $n)
                           ->orWhere('id', $n);
                    }
                });
            })
            ->when($producto !== '', fn($query) => $query->where('producto', 'like', "%{$producto}%"))
            ->when($batch !== null && $batch !== '', fn($query) => $query->where('numero_batch', (int)$batch))
            ->when($f_from, fn($query) => $query->whereDate('fecha_elaboracion', '>=', $f_from))
            ->when($f_to,   fn($query) => $query->whereDate('fecha_elaboracion', '<=', $f_to))
            ->when($pdf === '1', fn($query) => $query->whereNotNull('pdf_path'))
            ->when($pdf === '0', fn($query) => $query->whereNull('pdf_path'))
            ->orderByDesc('fecha_elaboracion')
            ->orderByDesc('id')
            ->paginate(10);

        $productos = Certificado::query()
            ->select('producto')->distinct()->orderBy('producto')->limit(100)->pluck('producto');

        return view('certificados.index', compact('certs', 'productos'));
    }

    public function create()
    {
        return view('certificados.create');
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'fecha_elaboracion' => ['required','date'],
            'producto'          => ['required','string','max:150'],
            'origen_cultivo'    => ['required','string','max:150'],
            'numero_batch'      => ['required','integer','min:1'],
            'cantidad_cubetas'  => ['required','integer','min:1'],
            'peso_por_cubeta'   => ['required','regex:/^\d{1,3}(\.\d{2})$/'],
            'color'             => ['nullable','string','max:100'],
            'olor'              => ['nullable','string','max:100'],
            'apariencia'        => ['nullable','string','max:120'],
            'sabor'             => ['nullable','string','max:100'],
        ]);

        $d['producto']        = Str::upper($d['producto']);
        $d['peso_por_cubeta'] = number_format((float)$d['peso_por_cubeta'], 2, '.', '');

        // Valores fijos
        $d['color']      = $d['color']      ?: 'Característico.';
        $d['olor']       = $d['olor']       ?: 'Característico.';
        $d['apariencia'] = $d['apariencia'] ?: 'Característica.';
        $d['sabor']      = $d['sabor']      ?: 'Característico.';

        $d['kilogramos_total'] = round(((int)$d['cantidad_cubetas']) * (float)$d['peso_por_cubeta'], 3);

        $c = Certificado::create($d);

        // Crear PDF
        $pdf  = Pdf::loadView('pdf.certificado', ['c' => $c]);
        $nombreArchivo = "certificado-{$c->id}.pdf";
        $rutaEnDrive = "certificados/{$nombreArchivo}";
         // Subir a Google Drive
        Storage::disk('google')->put($rutaEnDrive, $pdf->output());
        $c->update(['pdf_path' => $rutaEnDrive]); // Almacenar la ruta en la base de datos

        return redirect()->route('certificados.index')->with('success','Certificado guardado y PDF generado.');
    }

    public function edit(Certificado $certificado)
    {
        return view('certificados.edit', compact('certificado'));
    }

    public function update(Request $r, Certificado $certificado)
    {
        $d = $r->validate([
            'fecha_elaboracion' => ['required','date'],
            'producto'          => ['required','string','max:150'],
            'origen_cultivo'    => ['required','string','max:150'],
            'numero_batch'      => ['required','integer','min:1'],
            'cantidad_cubetas'  => ['required','integer','min:1'],
            'peso_por_cubeta'   => ['required','regex:/^\d{1,3}(\.\d{2})$/'],
            'color'             => ['nullable','string','max:100'],
            'olor'              => ['nullable','string','max:100'],
            'apariencia'        => ['nullable','string','max:120'],
            'sabor'             => ['nullable','string','max:100'],
        ]);

        $d['producto']        = Str::upper($d['producto']);
        $d['peso_por_cubeta'] = number_format((float)$d['peso_por_cubeta'], 2, '.', '');
        $d['color']      = $d['color']      ?: 'Característico.';
        $d['olor']       = $d['olor']       ?: 'Característico.';
        $d['apariencia'] = $d['apariencia'] ?: 'Característica.';
        $d['sabor']      = $d['sabor']      ?: 'Característico.';
        $d['kilogramos_total'] = round(((int)$d['cantidad_cubetas']) * (float)$d['peso_por_cubeta'], 3);

        $certificado->update($d);

        // Regenerar PDF
        $pdf  = Pdf::loadView('pdf.certificado', ['c' => $certificado->fresh()]);
        $nombreArchivo = "certificados/certificado-{$certificado->id}.pdf";
        $rutaEnDrive = "certificados/{$nombreArchivo}";

        if ($certificado->pdf_path) {
        Storage::disk('google')->delete($certificado->pdf_path);
        }

        Storage::disk('google')->put($rutaEnDrive, $pdf->output());
        $certificado->update(['pdf_path' => $rutaEnDrive]);

        return redirect()->route('certificados.index')->with('success','Certificado actualizado.');
    }

    public function destroy(Certificado $certificado)
    {
        if ($certificado->pdf_path) {
            Storage::disk('google')->delete($certificado->pdf_path);
        }
        $certificado->delete();

        return back()->with('success', 'Eliminado.');
    }

    public function descargar(Certificado $certificado)
    {
        abort_unless($certificado->pdf_path && Storage::disk('google')->exists($certificado->pdf_path), 404);
        return Storage::disk('google')->download($certificado->pdf_path);
    }

   public function ver(Certificado $certificado)
{
    abort_unless($certificado->pdf_path && Storage::disk('google')->exists($certificado->pdf_path), 404);
    $contenido = Storage::disk('google')->get($certificado->pdf_path);
    return response($contenido, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . basename($certificado->pdf_path) . '"',
        'X-Frame-Options' => 'ALLOWALL'
    ]);
}



}
