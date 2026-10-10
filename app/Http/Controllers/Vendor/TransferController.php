<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\StockTransfer;
use App\Services\Inventory\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sending stock to another branch, and confirming stock that arrived. Shown on the Stock page. */
class TransferController extends Controller
{
    public function store(Request $request, TransferService $transfers)
    {
        $from = Pharmacy::current();
        abort_unless($from, 403);

        $data = $request->validate([
            'to_branch' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:250'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'to_branch.required' => 'Choose the branch to send the stock to.',
            'items.required' => 'Add at least one product to the transfer.',
            'items.*.quantity.min' => 'Every product needs a quantity of at least 1.',
        ]);

        // Only a branch of the same organization can be sent to.
        $to = Pharmacy::where('organization_id', $from->organization_id)->whereKey($data['to_branch'])->first();

        if (! $to) {
            return back()->withInput()->with('error', 'That branch is not part of your organization.');
        }

        $lines = [];
        foreach ($data['items'] as $line) {
            $lines[(int) $line['medication_id']] = ($lines[(int) $line['medication_id']] ?? 0) + (int) $line['quantity'];
        }

        try {
            $transfer = $transfers->send($from, $to, $lines, Auth::user(), $data['note'] ?? null);
        } catch (\DomainException $problem) {
            return back()->withInput()->with('error', $problem->getMessage());
        }

        return redirect()->route('vendor.stock.receive', ['tab' => 'transfers'])
            ->with('success', "Transfer {$transfer->reference} is on its way to {$to->name}. They confirm it when it arrives.");
    }

    public function receive(StockTransfer $transfer, TransferService $transfers)
    {
        // Confirmed by the branch it was sent to.
        abort_unless(Pharmacy::current()?->id === $transfer->to_pharmacy_id, 404);

        return $this->finish(fn () => $transfers->receive($transfer, Auth::user()), "Transfer {$transfer->reference} received. The stock is on your shelf.");
    }

    public function cancel(StockTransfer $transfer, TransferService $transfers)
    {
        // Taken back by the branch that sent it.
        abort_unless(Pharmacy::current()?->id === $transfer->from_pharmacy_id, 404);

        return $this->finish(fn () => $transfers->cancel($transfer, Auth::user()), "Transfer {$transfer->reference} cancelled. The stock is back on your shelf.");
    }

    private function finish(callable $action, string $done)
    {
        try {
            $action();
        } catch (\DomainException $problem) {
            return redirect()->route('vendor.stock.receive', ['tab' => 'transfers'])->with('error', $problem->getMessage());
        }

        return redirect()->route('vendor.stock.receive', ['tab' => 'transfers'])->with('success', $done);
    }
}
