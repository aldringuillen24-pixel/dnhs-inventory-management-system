<?php

namespace App\Http\Controllers;

use App\Models\ArrivalInspection;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function pendingApproval()
    {
        $inspections = ArrivalInspection::query()
            ->where('status', 'inspected')
            ->where(function ($query) {
                $query->whereNull('acceptance_status')
                    ->orWhere('acceptance_status', '!=', 'rejected');
            })
            ->with([
                'items:id,arrival_inspection_id,stock_property_number,description,unit,quantity',
                'creator:id,first_name,last_name,username',
            ])
            ->orderBy('inspected_at')
            ->get();

        return response()->json(['inspections' => $inspections]);
    }

    public function approve(Request $request, ArrivalInspection $arrivalInspection)
    {
        $validated = $request->validate([
            'signature_name' => ['required', 'string', 'max:255'],
            'confirmation' => ['required', 'accepted'],
        ]);
        $user = $request->user();
        $accountName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username;

        if (mb_strtolower(trim($validated['signature_name'])) !== mb_strtolower($accountName)) {
            throw ValidationException::withMessages([
                'signature_name' => ['Enter your name as it appears on your account to sign this approval.'],
            ]);
        }

        DB::transaction(function () use ($arrivalInspection, $user, $validated): void {
            $inspection = ArrivalInspection::query()
                ->whereKey($arrivalInspection->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($inspection->status === 'inspected', 409, 'This IAR is no longer awaiting approval.');
            abort_if($inspection->acceptance_status === 'rejected', 409, 'A fully rejected delivery cannot be approved.');

            $inspection->update([
                'status' => 'approved',
                'completed_by' => $user->id,
                'completed_at' => now(),
                'custodian_signature_name' => trim($validated['signature_name']),
            ]);
        });

        return response()->json(['message' => 'IAR approved and signed.']);
    }

    public function returnForCorrection(Request $request, ArrivalInspection $arrivalInspection)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($arrivalInspection, $validated): void {
            $inspection = ArrivalInspection::query()
                ->whereKey($arrivalInspection->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($inspection->status === 'inspected', 409, 'This IAR is no longer awaiting approval.');

            $inspection->update([
                'status' => 'returned',
                'custodian_return_reason' => trim($validated['reason']),
                'custodian_returned_at' => now(),
            ]);
        });

        return response()->json(['message' => 'IAR returned to the Inspector for correction.']);
    }

    public function pdf(Request $request, ArrivalInspection $arrivalInspection)
    {
        $this->authorizeOwnership($request, $arrivalInspection);

        abort_unless(in_array($arrivalInspection->status, ['inspected', 'approved', 'posted'], true), 409, 'Record inspection results before generating the IAR.');
        abort_if($arrivalInspection->acceptance_status === 'rejected', 409, 'A fully rejected delivery does not have an IAR.');
        abort_unless($arrivalInspection->iar_number, 409, 'An IAR number is required before generating the report.');

        $arrivalInspection->load('items');
        $pdf = Pdf::loadView('pdfs.inspection-acceptance-report', [
            'inspection' => $arrivalInspection,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();
        $footerSeal = public_path('images/footer/escalante_city_education_seal.svg');
        $pdf->getDomPDF()->getCanvas()->page_script(static function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($footerSeal): void {
            $font = $fontMetrics->getFont('Times-Roman', 'normal');

            if ($pageNumber === $pageCount) {
                $pageWidth = $canvas->get_width();
                $pageHeight = $canvas->get_height();
                $leftMargin = 40;
                $sealSize = 34;
                $footerTop = $pageHeight - 78;
                $textX = $leftMargin + $sealSize + 10;
                $fontSize = 8.5;
                $lineHeight = 11;
                $footerLines = [
                    'Escalante City, Negros Occidental, Philippines',
                    'Tel. Nos. - (034) 724-5720, (034) 454-0746',
                    'Escalante.city001@deped.gov.ph',
                    'Depedescalantecity.weebly.com',
                ];
                $sealTop = $footerTop + 7 + (($lineHeight * (count($footerLines) - 1) - $sealSize) / 2);

                $canvas->line($leftMargin, $footerTop - 8, $pageWidth - $leftMargin, $footerTop - 8, [0, 0, 0], 0.7);
                $canvas->image($footerSeal, $leftMargin, $sealTop, $sealSize, $sealSize);

                foreach ($footerLines as $index => $line) {
                    $canvas->text(
                        $textX,
                        $footerTop + 7 + ($index * $lineHeight),
                        $line,
                        $font,
                        $fontSize,
                        [0, 0, 0],
                    );
                }
            }

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

        if (! in_array($arrivalInspection->status, ['draft', 'returned'], true)) {
            abort(409, 'Submitted or approved inspections can no longer be edited.');
        }

        $validated = $this->validateInspection($request);

        DB::transaction(function () use ($arrivalInspection, $validated, $request) {
            $arrivalInspection->update(
                $this->headerAttributes($validated) + [
                    'status' => $validated['status'],
                    'inspected_by' => $validated['status'] === 'inspected' ? $arrivalInspection->inspected_by ?? $request->user()->id : null,
                    'inspected_at' => $validated['status'] === 'inspected'
                        ? ($arrivalInspection->status === 'inspected' ? $arrivalInspection->inspected_at ?? now() : now())
                        : null,
                    'custodian_return_reason' => $validated['status'] === 'inspected' ? null : $arrivalInspection->custodian_return_reason,
                    'custodian_returned_at' => $validated['status'] === 'inspected' ? null : $arrivalInspection->custodian_returned_at,
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

    public function updateProfile(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->first_name = $validated['first_name'] ?? null;
        $user->last_name = $validated['last_name'] ?? '';
        $user->building = $validated['building'] ?? null;
        $user->room = $validated['room'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('inspector.profile')->with('success', 'Profile updated successfully.');
    }
}
