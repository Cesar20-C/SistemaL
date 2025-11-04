<?php

namespace App\Http\Controllers;

use App\Models\EtiquetaLote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EtiquetaController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim((string) $request->get('q'));
        $producto  = trim((string) $request->get('producto'));
        $elab_from = $request->get('elab_from');
        $elab_to   = $request->get('elab_to');
        $ven_from  = $request->get('ven_from');
        $ven_to    = $request->get('ven_to');
        $pdf       = $request->get('pdf');

        $lotes = EtiquetaLote::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('producto', 'like', "%{$q}%");
                    if (ctype_digit($q)) {
                        $qq->orWhere('id', (int) $q);
                    }
                });
            })
            ->when($producto !== '', fn ($query) => $query->where('producto', 'like', "%{$producto}%"))
            ->when($elab_from, fn ($query) => $query->whereDate('fecha_elaboracion', '>=', $elab_from))
            ->when($elab_to,   fn ($query) => $query->whereDate('fecha_elaboracion', '<=', $elab_to))
            ->when($ven_from,  fn ($query) => $query->whereDate('fecha_vencimiento', '>=', $ven_from))
            ->when($ven_to,    fn ($query) => $query->whereDate('fecha_vencimiento', '<=', $ven_to))
            ->when($pdf === '1', fn ($query) => $query->whereNotNull('pdf_path'))
            ->when($pdf === '0', fn ($query) => $query->whereNull('pdf_path'))
            ->orderByDesc('id')
            ->paginate(12);

        $productos = EtiquetaLote::select('producto')->distinct()->orderBy('producto')->limit(100)->pluck('producto');

        return view('etiquetas.index', compact('lotes', 'productos'));
    }

    public function create()
    {
        $hoy = now()->format('Y-m-d');
        return view('etiquetas.create', compact('hoy'));
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'fecha_elaboracion' => ['required', 'date'],
            'producto'          => ['required', 'in:CEBOLLA EN CUBOS,JALAPEÑO EN CUBOS,PIMIENTO EN CUBOS'],
            'peso_kg'           => ['required', 'regex:/^\d{1,3}(\.\d{2})$/'],
            'numero_inicial'    => ['required', 'integer', 'min:1'],
            'cantidad'          => ['required', 'integer', 'min:1', 'max:2000'],
        ]);

        $d['producto']          = Str::upper($d['producto']);
        $d['fecha_vencimiento'] = Carbon::parse($d['fecha_elaboracion'])->addDays(2)->toDateString();
        $d['peso_kg']           = number_format((float) $d['peso_kg'], 2, '.', '');

        $lote = EtiquetaLote::create($d);
        $labels = $this->buildLabelsData($lote);

        $logoBase64 = null;
        $logoPath   = public_path('imagen/Logo.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $pdf = Pdf::loadView('etiquetas.pdf', [
            'lote'   => $lote,
            'labels' => $labels,
            'logo'   => $logoBase64,
        ])->setPaper('letter');

        // === Guardar en Google Drive ===
        $nombreArchivo = "etiquetas/lote_{$lote->id}.pdf";
        Storage::disk('google')->put($nombreArchivo, $pdf->output());
        $lote->update(['pdf_path' => $nombreArchivo]);

        return redirect()->route('etiquetas.index')->with('success', 'Lote guardado y PDF generado en Drive.');
    }

    public function ver(EtiquetaLote $lote)
    {
        abort_unless($lote->pdf_path && Storage::disk('google')->exists($lote->pdf_path), 404);

        $contenido = Storage::disk('google')->get($lote->pdf_path);
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="lote_'.$lote->id.'.pdf"',
            'X-Frame-Options' => 'ALLOWALL'
        ]);
    }

    public function descargar(EtiquetaLote $lote)
    {
        abort_unless($lote->pdf_path && Storage::disk('google')->exists($lote->pdf_path), 404);
        return Storage::disk('google')->download($lote->pdf_path);
    }

    public function destroy(EtiquetaLote $lote)
    {
        if ($lote->pdf_path && Storage::disk('google')->exists($lote->pdf_path)) {
            Storage::disk('google')->delete($lote->pdf_path);
        }
        $lote->delete();
        return redirect()->route('etiquetas.index')->with('success', 'Lote eliminado.');
    }

    private function buildLabelsData(EtiquetaLote $lote): array
    {
        $labels = [];
        $desde  = (int) $lote->numero_inicial;
        $hasta  = $desde + (int) $lote->cantidad - 1;

        for ($n = $desde; $n <= $hasta; $n++) {
            $labels[] = [
                'empresa_linea1' => 'Distribuidora de Insumos Industriales',
                'empresa_linea2' => '& Alimenticios D I P I & I',
                'fecha_elab'     => $lote->fecha_elaboracion->format('d/m/Y'),
                'fecha_ven'      => $lote->fecha_vencimiento->format('d/m/Y'),
                'producto'       => Str::upper($lote->producto),
                'peso'           => number_format($lote->peso_kg, 2, '.', '') . ' kg',
                'numero'         => $n,
            ];
        }

        return $labels;
    }
}
