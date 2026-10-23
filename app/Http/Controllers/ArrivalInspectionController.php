<?php

namespace App\Http\Controllers;

use App\Models\ArrivalInspection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArrivalInspectionController extends Controller
{
    public function index(Request $request)
    {
        $inspections = ArrivalInspection::query()
            ->where('created_by', $request->user()->id)
            ->with('items:id,arrival_inspection_id,stock_property_number,description,unit,quantity')
            ->withCount('items')
            ->latest()
            ->get();

        return response()->json(['inspections' => $inspections]);
    }

    public function show(Request $request, ArrivalInspection $arrivalInspection)
    {
        $this->authorizeOwnership($request, $arrivalInspection);

        return response()->json([
            'inspection' => $arrivalInspection->load('items'),
        ]);
    }

    public function pdf(Request $request, ArrivalInspection $arrivalInspection)
    {
        $this->authorizeOwnership($request, $arrivalInspection);

        abort_unless($arrivalInspection->status === 'inspected', 409, 'Record inspection results before generating the IAR.');
        abort_if($arrivalInspection->acceptance_status === 'rejected', 409, 'A fully rejected delivery does not have an IAR.');
        abort_unless($arrivalInspection->iar_number, 409, 'An IAR number is required before generating the report.');

        $arrivalInspection->load('items');
        $pdf = Pdf::loadView('pdfs.inspection-acceptance-report', [
            'inspection' => $arrivalInspection,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('Times-Roman', 'normal');
        $pdf->getDomPDF()->getCanvas()->page_script(static function ($pageNumber, $pageCount, $canvas) use ($font): void {
            $canvas->text(
                $canvas->get_width() - 34,
                $canvas->get_height() - 24,
                (string) $pageNumber,
                $font,
                8,
                [0, 0, 0],
            );
        });
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $arrivalInspection->iar_number).'.pdf';

        return $pdf->stream($filename);
    }

    public function store(Request $request)
    {
        $validated = $this->validateInspection($request);

        $inspection = DB::transaction(function () use ($request, $validated) {
            $inspection = ArrivalInspection::query()->create(
                $this->headerAttributes($validated) + [
                    'status' => $validated['status'],
                    'created_by' => $request->user()->id,
                    'inspected_by' => $validated['status'] === 'inspected' ? $request->user()->id : null,
                    'inspected_at' => $validated['status'] === 'inspected' ? now() : null,
                ]
            );
            $inspection->items()->createMany($validated['items']);

            return $inspection;
        });

        return response()->json([
            'message' => $inspection->status === 'inspected'
                ? 'Inspection results saved.'
                : 'Inspection draft saved.',
            'inspection' => $inspection->load('items'),
        ], 201);
    }

    public function update(Request $request, ArrivalInspection $arrivalInspection)
    {
        $this->authorizeOwnership($request, $arrivalInspection);

        if (in_array($arrivalInspection->status, ['finalized', 'posted'], true)) {
            abort(409, 'Finalized inspections can no longer be edited.');
        }

        $validated = $this->validateInspection($request);

        DB::transaction(function () use ($arrivalInspection, $validated, $request) {
            $arrivalInspection->update(
                $this->headerAttributes($validated) + [
                    'status' => $validated['status'],
                    'inspected_by' => $validated['status'] === 'inspected' ? $arrivalInspection->inspected_by ?? $request->user()->id : null,
                    'inspected_at' => $validated['status'] === 'inspected' ? $arrivalInspection->inspected_at ?? now() : null,
                ]
            );
            $arrivalInspection->items()->delete();
            $arrivalInspection->items()->createMany($validated['items']);
        });

        return response()->json([
            'message' => $arrivalInspection->status === 'inspected'
                ? 'Inspection results saved.'
                : 'Inspection draft saved.',
            'inspection' => $arrivalInspection->fresh()->load('items'),
        ]);
    }

    public function destroy(Request $request, ArrivalInspection $arrivalInspection)
    {
        $this->authorizeOwnership($request, $arrivalInspection);
        abort_unless($arrivalInspection->status === 'draft', 409, 'Only draft inspections can be deleted.');

        DB::transaction(function () use ($arrivalInspection): void {
            $arrivalInspection->items()->delete();
            $arrivalInspection->delete();
        });

        return response()->json(['message' => 'Inspection draft deleted.']);
    }

    private function validateInspection(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'inspected'])],
            'inspection_verified' => ['required', 'boolean'],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'supplier_name' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'contract_title' => ['nullable', 'string', 'max:255'],
            'requesting_office' => ['nullable', 'string', 'max:255'],
            'responsibility_center_code' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'iar_number' => ['nullable', 'string', 'max:255', Rule::unique('arrival_inspections', 'iar_number')->ignore($request->route('arrivalInspection'))],
            'iar_date' => ['nullable', 'date'],
            'acceptance_status' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', Rule::in(['complete', 'partial', 'rejected'])],
            'acceptance_remarks' => ['nullable', 'string'],
            'inspection_date' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'date'],
            'receipt_date' => ['nullable', 'date'],
            'committee_member_1_name' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'committee_member_1_designation' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'committee_member_2_name' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'committee_member_2_designation' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'committee_member_3_name' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'committee_member_3_designation' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'custodian_name' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'custodian_designation' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'items' => ['present', 'array', 'max:100'],
            'items.*.stock_property_number' => ['nullable', 'string', 'max:255'],
            'items.*.description' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string'],
            'items.*.unit' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'string', 'max:255'],
            'items.*.quantity' => [Rule::requiredIf($request->input('status') === 'inspected'), 'nullable', 'integer', 'min:1'],
        ]);

        if ($validated['status'] === 'inspected' && $validated['acceptance_status'] === 'rejected' && ! empty($validated['iar_number'])) {
            throw ValidationException::withMessages([
                'iar_number' => ['A fully rejected delivery is recorded without an IAR number.'],
            ]);
        }

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['status', 'items'])
            ->all();
    }

    private function authorizeOwnership(Request $request, ArrivalInspection $inspection): void
    {
        abort_unless((int) $inspection->created_by === (int) $request->user()->id, 404);
    }
}
