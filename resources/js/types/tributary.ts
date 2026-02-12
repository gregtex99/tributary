export type ItemState =
    | 'action_needed'
    | 'waiting_on_reply'
    | 'waiting_on_calendar'
    | 'done'
    | 'cancelled'
    | string;

export interface ActionItemTransition {
    id: string;
    action_item_id: string;
    from_state: string;
    to_state: string;
    trigger: string;
    signal_data: Record<string, unknown> | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface NudgeLog {
    id: string;
    action_item_id: string;
    nudge_number: number;
    channel: string;
    message_text: string;
    approved_at: string | null;
    sent_at: string | null;
    response_detected_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface ActionItem {
    id: string;
    org_id: string;
    user_id: string;
    source: string;
    source_ref: string | null;
    title: string;
    description: string | null;
    current_state: ItemState;
    ball_with: string;
    waiting_for: string | null;
    nudge_after_hours: number;
    next_nudge_at: string | null;
    nudge_count: number;
    max_nudges: number;
    resolved_at: string | null;
    created_at: string;
    updated_at: string;
    transitions?: ActionItemTransition[];
    nudges?: NudgeLog[];
}

export interface NudgeRule {
    id: string;
    org_id: string;
    item_type: string;
    first_nudge_hours: number;
    repeat_nudge_hours: number;
    max_nudges: number;
    auto_send: boolean;
    escalation_after_nudges: number;
    created_at: string;
    updated_at: string;
}
