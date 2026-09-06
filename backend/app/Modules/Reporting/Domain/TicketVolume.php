<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

use Illuminate\Support\Facades\DB;

/**
 * How much arrived, and what shape it was.
 *
 * Six cards and three breakdowns, all over one range, all reading the same
 * table the ticket list reads. There is no warehouse, no read replica, no ETL
 * and no aggregation table — adding this module adds no infrastructure, which
 * is the difference between a report set that stays true and one that is true
 * as of the last successful sync.
 *
 * EVERY FIGURE CARRIES ITS OWN FILTERS. A number nobody can audit is a number
 * nobody believes, so each one ships the ticket-list query that produces
 * exactly the rows it counted — not a description of them, the filters
 * themselves, so the destination cannot drift from the figure.
 */
final class TicketVolume
{
    /** The five statuses plus the total. Written out; there is no seventh card. */
    private const STATUSES = ['open', 'pending', 'resolved', 'closed'];

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(ReportPeriod $period): array
    {
        /*
         * One pass for all six, not six queries. They are the same rows
         * counted differently, and six round trips would let them disagree —
         * a ticket resolved between the third query and the fourth would be
         * counted in neither, or in both.
         */
        $row = DB::table('tickets')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('count(*) as total')
            ->selectRaw("count(case when status = 'open' then 1 end) as open_count")
            ->selectRaw("count(case when status = 'pending' then 1 end) as pending_count")
            ->selectRaw("count(case when status = 'resolved' then 1 end) as resolved_count")
            ->selectRaw("count(case when status = 'closed' then 1 end) as closed_count")
            ->first();

        return [
            'cards' => $this->cards($period, $row),
            'by_status' => $this->byStatus($period),
            'by_category' => $this->byCategory($period),
            'by_assignee' => $this->byAssignee($period),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cards(ReportPeriod $period, ?object $row): array
    {
        $base = $period->asTicketListFilters();

        $cards = [[
            'key' => 'total',
            'value' => (int) ($row?->total ?? 0),
            // No status filter: the total is every ticket in the range.
            'filters' => $base,
        ]];

        foreach (self::STATUSES as $status) {
            $cards[] = [
                'key' => $status,
                'value' => (int) ($row?->{$status.'_count'} ?? 0),
                'filters' => [...$base, 'status' => $status],
            ];
        }

        return $cards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byStatus(ReportPeriod $period): array
    {
        $rows = DB::table('tickets')
            ->whereBetween('created_at', [$period->from, $period->to])
            ->groupBy('status')
            ->orderByDesc(DB::raw('count(*)'))
            ->get(['status', DB::raw('count(*) as total')]);

        return $rows->map(fn (object $r): array => [
            'key' => (string) $r->status,
            'label' => (string) $r->status,
            'value' => (int) $r->total,
            'filters' => [...$period->asTicketListFilters(), 'status' => (string) $r->status],
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byCategory(ReportPeriod $period): array
    {
        /*
         * Read through the query builder, not through Tickets' Category model.
         * This module is above Tickets and could import it, but a report that
         * depended on that aggregate would break whenever the aggregate
         * changed — and reports are the surface nobody notices is broken.
         */
        $column = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        $rows = DB::table('tickets')
            ->leftJoin('ticket_categories', 'ticket_categories.id', '=', 'tickets.category_id')
            ->whereBetween('tickets.created_at', [$period->from, $period->to])
            ->groupBy('tickets.category_id', 'ticket_categories.'.$column)
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'tickets.category_id as category_id',
                'ticket_categories.'.$column.' as name',
                DB::raw('count(*) as total'),
            ]);

        return $rows->map(function (object $r) use ($period): array {
            $id = $r->category_id === null ? null : (int) $r->category_id;

            return [
                'key' => $id === null ? 'none' : (string) $id,
                /*
                 * Uncategorised is a real answer and often the interesting
                 * one — a desk where a third of the month has no category has
                 * a problem this row is the only place to see.
                 */
                'label' => $r->name === null ? null : (string) $r->name,
                'value' => (int) $r->total,
                'filters' => $id === null
                    ? $period->asTicketListFilters()
                    : [...$period->asTicketListFilters(), 'category_id' => $id],
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byAssignee(ReportPeriod $period): array
    {
        $rows = DB::table('tickets')
            ->leftJoin('users', 'users.id', '=', 'tickets.assignee_id')
            ->whereBetween('tickets.created_at', [$period->from, $period->to])
            ->groupBy('tickets.assignee_id', 'users.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'tickets.assignee_id as assignee_id',
                'users.name as name',
                DB::raw('count(*) as total'),
            ]);

        return $rows->map(function (object $r) use ($period): array {
            $id = $r->assignee_id === null ? null : (int) $r->assignee_id;

            return [
                'key' => $id === null ? 'unassigned' : (string) $id,
                'label' => $r->name === null ? null : (string) $r->name,
                'value' => (int) $r->total,
                'filters' => $id === null
                    ? $period->asTicketListFilters()
                    : [...$period->asTicketListFilters(), 'assignee_id' => $id],
            ];
        })->all();
    }
}
