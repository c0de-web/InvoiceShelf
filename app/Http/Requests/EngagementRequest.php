<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Engagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EngagementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->header('company');
        $isProject = $this->input('type') === Engagement::TYPE_PROJECT;

        return [
            'type' => ['required', Rule::in(Engagement::TYPES)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(Engagement::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'auto_invoice' => ['nullable', 'boolean'],
            'exchange_rate' => [
                Rule::requiredIf(fn () => $this->customerUsesAnotherCurrency()),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'estimate_id' => ['nullable', 'integer', Rule::exists('estimates', 'id')->where('company_id', $companyId)],
            'contract_value' => [Rule::requiredIf($isProject), 'nullable', 'integer', 'min:1'],
            'milestones' => [Rule::requiredIf($isProject), 'array', $isProject ? 'min:1' : 'max:0'],
            'milestones.*.id' => ['nullable', 'integer'],
            'milestones.*.name' => ['required', 'string', 'max:191'],
            'milestones.*.percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'milestones.*.amount' => ['required_without:milestones.*.percent', 'nullable', 'integer', 'min:0'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.settled_at' => ['nullable', 'date'],
            'milestones.*.settled_reference' => ['nullable', 'string', 'max:191'],
            'recurring_invoice_ids' => ['nullable', 'array'],
            'recurring_invoice_ids.*' => [
                'integer',
                Rule::exists('recurring_invoices', 'id')
                    ->where('company_id', $companyId)
                    ->where('customer_id', $this->input('customer_id')),
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== Engagement::TYPE_PROJECT || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $contract = (int) $this->input('contract_value');
                $milestones = $this->milestonesWithAmounts();
                $engagement = $this->route('engagement');
                $locked = $engagement ? $engagement->milestones->filter->isLocked() : collect();

                if ($contract < $locked->sum('amount')) {
                    $validator->errors()->add('contract_value', 'The contract value cannot be less than what is already invoiced or settled.');
                }

                foreach ($locked as $milestone) {
                    $sent = collect($milestones)->firstWhere('id', $milestone->id);

                    if (! $sent || $sent['name'] !== $milestone->name || $sent['amount'] !== $milestone->amount) {
                        $validator->errors()->add('milestones', "\"{$milestone->name}\" is already invoiced or settled, so it can't be removed or changed.");
                    }
                }

                $ids = collect($milestones)->pluck('id')->filter();
                if ($ids->diff($engagement?->milestones->pluck('id') ?? collect())->isNotEmpty()) {
                    $validator->errors()->add('milestones', 'A milestone belongs to another engagement.');
                }

                if (array_sum(array_column($milestones, 'amount')) !== $contract) {
                    $validator->errors()->add('milestones', 'The milestones must add up to the contract value.');
                }
            },
        ];
    }

    /**
     * The milestones with every amount in minor units: a percentage becomes its share of the
     * contract, and when the last milestone is a percentage it takes whatever rounding left over.
     *
     * @return array<int, array<string, mixed>>
     */
    public function milestonesWithAmounts(): array
    {
        $contract = (int) $this->input('contract_value');
        $milestones = array_values((array) $this->input('milestones', []));
        $lastIndex = count($milestones) - 1;

        foreach ($milestones as $index => &$milestone) {
            $percent = $milestone['percent'] ?? null;

            if ($percent !== null && $percent !== '') {
                $milestone['amount'] = (int) floor($contract * (float) $percent / 100);
            } else {
                $milestone['percent'] = null;
                $milestone['amount'] = (int) ($milestone['amount'] ?? 0);
            }

            $milestone['id'] = isset($milestone['id']) ? (int) $milestone['id'] : null;
            $milestone['position'] = $index + 1;
        }
        unset($milestone);

        if ($lastIndex >= 0 && $milestones[$lastIndex]['percent'] !== null) {
            $others = array_sum(array_column(array_slice($milestones, 0, $lastIndex), 'amount'));
            $milestones[$lastIndex]['amount'] = $contract - $others;
        }

        return $milestones;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEngagementPayload(): array
    {
        $isProject = $this->input('type') === Engagement::TYPE_PROJECT;

        return [
            ...$this->safe()->only(['type', 'customer_id', 'name', 'description', 'start_date', 'estimate_id', 'exchange_rate']),
            'status' => $this->input('status') ?: 'ACTIVE',
            'auto_invoice' => $isProject && $this->boolean('auto_invoice'),
            'contract_value' => $isProject ? (int) $this->input('contract_value') : null,
            'company_id' => $this->header('company'),
        ];
    }

    private function customerUsesAnotherCurrency(): bool
    {
        $customer = Customer::find($this->input('customer_id'));
        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        return $customer && $companyCurrency && (string) $customer->currency_id !== (string) $companyCurrency;
    }
}
