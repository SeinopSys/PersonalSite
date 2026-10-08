@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('bills.heading') }}<x-js-icon></x-js-icon></h2>
    <p>{{ __('bills.about') }}</p>

    <div id="bills-app"></div>
@endsection

@section('js-locales')
    {!! \App\Util\Core::ExportTranslations('bills', [
        'type-sewage', 'type-heating', 'type-electricity', 'type-water', 'bill-type', 'drop-label', 'parsing', 'review',
        'file', 'period', 'amount', 'due-date', 'invoice-number', 'status', 'status-paid', 'status-unpaid',
        'status-overdue', 'gap', 'overlap', 'trailing', 'no-bills', 'save-bills', 'discard', 'add-manual',
        'warn-no-period', 'warn-no-amount', 'warn-bad-range', 'warn-duplicate-sha256', 'warn-duplicate-invoice_number',
        'warn-duplicate-batch', 'warn-duplicate-period_amount', 'already-saved', 'dates-filled', 'find-bill', 'find-bill-help', 'choose-file', 'close-result', 'recorded', 'not-recorded', 'found-by-sha256', 'found-by-invoice_number', 'found-by-period_amount', 'linked-transactions', 'not-linked-yet', 'assign-heading', 'likely-transactions', 'link', 'find-transaction', 'tx-not-found', 'create-and-link', 'already-linked-here', 'paid-times', 'paid-times-settled', 'advance-invoice', 'advance-invoice-label', 'settlement-covers', 'invoice-kind', 'share-title', 'share-help', 'share-label', 'share-expiry', 'share-never', 'share-days', 'share-create', 'share-copy', 'share-copied', 'share-open', 'share-revoke', 'share-confirm-revoke', 'share-none', 'share-views', 'share-last-viewed', 'share-created', 'share-expires', 'share-expired', 'credit-applied', 'credit-applied-help', 'credit-line', 'credit-source', 'credit-source-none', 'credit-line-from', 'overpayment-credited', 'choose-folder', 'bills-and-note', 'group-selected', 'ungroup-selected', 'clear-selection', 'selected-count', 'select-transaction', 'group-label', 'group-total', 'sort-toggle', 'accounted-amount', 'accounted-amount-help', 'other-accounted', 'mark-accounted', 'mark-accounted-help', 'known-accounted', 'unaccounted', 'fee-or-unaccounted', 'unaccounted-summary', 'analyze', 'clear-queue', 'analyze-as', 'remove', 'note-image-period-from-name', 'note-period-from-name', 'file-preview', 'period-start', 'period-end', 'file-date', 'note-image-manual', 'match-before', 'match-after', 'already-linked', 'suggest-matches', 'no-suggestions', 'apply-matches', 'accept', 'warn-not-pdf', 'warn-parse-failed', 'saved', 'skipped', 'transactions',
        'add-transaction', 'edit-transaction', 'paid-on', 'note', 'linked-bills', 'no-transactions', 'transfer-fee', 'fee-negative', 'edit', 'delete', 'confirm-delete-bill', 'confirm-delete-transaction', 'no-unpaid-bills',
        'actions', 'loading', 'days', 'pagination', 'previous', 'next', 'period-issues',
    ]) !!}
    {!! \App\Util\Core::ExportTranslations('global', ['save', 'cancel', 'optional']) !!}
@endsection
