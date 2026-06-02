<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendlyFyzio — ER Diagram</title>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, 'Inter', sans-serif; background: #0d0d12; color: #e2e2e8; min-height: 100vh; }

        header {
            padding: 16px 36px;
            border-bottom: 1px solid #1c1c28;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #0d0d12;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .logo { width: 28px; height: 28px; background: #ED86A3; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        header h1 { font-size: 14px; font-weight: 600; color: #fff; }
        .pill { font-size: 11px; color: #555; background: #181820; border: 1px solid #252535; border-radius: 20px; padding: 2px 9px; }
        .spacer { flex: 1; }
        .badge-pkg { font-size: 10px; color: #6366f1; background: #1a1a2e; border: 1px solid #2a2a4e; border-radius: 4px; padding: 2px 7px; }

        nav {
            display: flex;
            padding: 0 36px;
            border-bottom: 1px solid #1c1c28;
            overflow-x: auto;
        }
        .tab {
            padding: 11px 15px;
            font-size: 12.5px;
            color: #555;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            transition: color 0.12s;
            user-select: none;
        }
        .tab:hover { color: #999; }
        .tab.active { color: #ED86A3; border-bottom-color: #ED86A3; font-weight: 500; }
        .tab sup { font-size: 9px; color: #3a3a4a; margin-left: 3px; }

        .panels { padding: 28px 36px; }
        .panel { display: none; }
        .panel.active { display: block; }

        .panel-head { margin-bottom: 20px; }
        .panel-head h2 { font-size: 17px; font-weight: 600; color: #fff; margin-bottom: 4px; }
        .panel-head p { font-size: 12px; color: #555; line-height: 1.5; }
        .panel-head p strong { color: #888; font-weight: 500; }

        .diagram-box {
            background: #111118;
            border: 1px solid #1c1c28;
            border-radius: 10px;
            padding: 24px 16px;
            overflow-x: auto;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .diagram-box svg { max-width: 100%; height: auto; display: block; }

        .spinner { color: #333; font-size: 12px; }
    </style>
</head>
<body>

<header>
    <div class="logo">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24"><rect x="2" y="2" width="9" height="9" rx="1.5" fill="white"/><rect x="13" y="2" width="9" height="9" rx="1.5" fill="white" opacity=".5"/><rect x="2" y="13" width="9" height="9" rx="1.5" fill="white" opacity=".5"/><rect x="13" y="13" width="9" height="9" rx="1.5" fill="white" opacity=".25"/></svg>
    </div>
    <h1>FriendlyFyzio OS — Database Schema</h1>
    <span class="pill">45 tables · UUIDs</span>
    <div class="spacer"></div>
    <span class="badge-pkg">ralphjsmit/filament-media-library</span>
    <span class="badge-pkg">spatie/laravel-tags</span>
</header>

<nav>
    <div class="tab active" onclick="show('overview')"  id="t-overview">Overview</div>
    <div class="tab"        onclick="show('identity')"  id="t-identity">Identity <sup>5</sup></div>
    <div class="tab"        onclick="show('clinic')"    id="t-clinic">Clinic <sup>5</sup></div>
    <div class="tab"        onclick="show('reserv')"    id="t-reserv">Reservations <sup>7</sup></div>
    <div class="tab"        onclick="show('courses')"   id="t-courses">Courses <sup>10</sup></div>
    <div class="tab"        onclick="show('workshops')" id="t-workshops">Workshops &amp; Extras <sup>4</sup></div>
    <div class="tab"        onclick="show('financial')" id="t-financial">Financial <sup>7</sup></div>
    <div class="tab"        onclick="show('cms')"       id="t-cms">CMS <sup>8</sup></div>
</nav>

<div class="panels">

    <div class="panel active" id="p-overview">
        <div class="panel-head">
            <h2>Overview</h2>
            <p>All major entities and how they connect. No columns shown — click a domain tab for detail.</p>
        </div>
        <div class="diagram-box"><div id="d-overview"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-identity">
        <div class="panel-head">
            <h2>Identity</h2>
            <p>
                All users share one table (role field: admin | therapist | customer).
                <strong>Anamnesis</strong> is a single nullable WYSIWYG on <code>client_profiles</code> — not a separate table.
                <strong>Specializations</strong> are a hasMany on therapist profiles.
                Photos via <strong>ralphjsmit/filament-media-library</strong> — no photo_path columns.
            </p>
        </div>
        <div class="diagram-box"><div id="d-identity"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-clinic">
        <div class="panel-head">
            <h2>Clinic</h2>
            <p>
                Buildings → rooms → therapist schedules.
                Which services can be held in which room is configured via the <code>service_rooms</code> pivot (see Reservations tab) — rooms themselves carry no type label.
            </p>
        </div>
        <div class="diagram-box"><div id="d-clinic"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-reserv">
        <div class="panel-head">
            <h2>Services &amp; Reservations</h2>
            <p>
                Service catalogue for physiotherapy and massage types.
                <strong>type</strong> on both service_categories and services is nullable — not all services need a type.
                <strong>duration_blocks</strong> / <strong>break_blocks</strong>: 1 block = 15 min (e.g. 90 min session = 6 blocks, 15 min break = 1 block).
                <strong>price</strong> is an integer in CZK.
                <strong>visibility</strong>: <em>public</em> = everyone · <em>clients</em> = logged-in clients · <em>invite</em> = token link only (see invitations table) · <em>hidden</em> = admin/manager only, never surfaced to any client.
                <strong>invitations</strong> is polymorphic — the same table covers invite-only services, courses, and workshops. Replaces the one-off <code>presale_token</code> on course_series.
            </p>
        </div>
        <div class="diagram-box"><div id="d-reserv"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-courses">
        <div class="panel-head">
            <h2>Courses &amp; Lessons</h2>
            <p>
                <strong>published_at</strong> replaces boolean <em>active</em> on both course_categories and courses.
                <strong>price</strong> is an integer in CZK (on course_series and one_time_lessons).
                Photos via media library. Substitute tokens are generated per lesson_attendance row on early cancellation.
            </p>
        </div>
        <div class="diagram-box"><div id="d-courses"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-workshops">
        <div class="panel-head">
            <h2>Workshops, Waitlist &amp; Reviews</h2>
            <p>
                Workshops use <strong>published_at</strong> instead of boolean active.
                <strong>waitlist_entries</strong> and <strong>reviews</strong> are polymorphic — they attach to series, workshops, or one-time lessons.
                Tags (VIP, new client, etc.) on any model via <strong>spatie/laravel-tags</strong>.
            </p>
        </div>
        <div class="diagram-box"><div id="d-workshops"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-financial">
        <div class="panel-head">
            <h2>Financial</h2>
            <p>
                All monetary fields are integers in <strong>CZK</strong> (no subunits — Czech prices are whole numbers).
                <strong>client_snapshot</strong> on invoices freezes billing details at time of issue so historical invoices are unaffected by profile edits.
                Invoice and receipt PDFs via media library. Credit validity tracked per transaction via <strong>expires_at</strong>.
            </p>
        </div>
        <div class="diagram-box"><div id="d-financial"><span class="spinner">Rendering…</span></div></div>
    </div>

    <div class="panel" id="p-cms">
        <div class="panel-head">
            <h2>CMS</h2>
            <p>
                <strong>published_at</strong> replaces boolean published on pages.
                Banner and modal images via media library.
                Navigation supports nested items via self-referential <code>parent_id</code>.
                Newsletter opt-in is tracked via <code>users.newsletter_opted_in_at</code> only —
                Mailjet is the source of truth for subscriber lists, no local mirror needed.
            </p>
        </div>
        <div class="diagram-box"><div id="d-cms"><span class="spinner">Rendering…</span></div></div>
    </div>

</div>

<script>
const DIAGRAMS = {

overview: `erDiagram
    USERS ||--o| CLIENT_PROFILES : "has profile"
    USERS ||--o| THERAPIST_PROFILES : "has profile"
    THERAPIST_PROFILES ||--o{ THERAPIST_SPECIALIZATIONS : "has"
    USERS ||--o{ THERAPY_RECORDS : "receives"
    BUILDINGS ||--o{ ROOMS : "contains"
    USERS ||--o{ THERAPIST_WEEKLY_SCHEDULES : "works"
    ROOMS ||--o{ THERAPIST_WEEKLY_SCHEDULES : "used in"
    USERS ||--o{ CALENDAR_BLOCKS : "blocked"
    SERVICE_CATEGORIES ||--o{ SERVICES : "groups"
    SERVICES ||--o{ RESERVATIONS : "type"
    USERS ||--o{ RESERVATIONS : "client"
    ROOMS ||--o{ RESERVATIONS : "room"
    COURSE_CATEGORIES ||--o{ COURSES : "groups"
    COURSES ||--o{ COURSE_SERIES : "has"
    COURSE_SERIES ||--o{ COURSE_LESSONS : "has"
    USERS ||--o{ COURSE_ENROLLMENTS : "enrolls"
    COURSE_SERIES ||--o{ COURSE_ENROLLMENTS : "in"
    WORKSHOPS ||--o{ WORKSHOP_REGISTRATIONS : "has"
    USERS ||--o{ WORKSHOP_REGISTRATIONS : "registers"
    INVOICE_SERIES ||--o{ INVOICES : "numbers"
    USERS ||--o{ INVOICES : "receives"
    INVOICES ||--o{ PAYMENTS : "settled by"
    USERS ||--|| CREDIT_ACCOUNTS : "has"
    PAGES ||--o{ PAGE_BLOCKS : "has"
    NAVIGATIONS ||--o{ NAVIGATION_ITEMS : "has"`,

identity: `erDiagram
    users {
        uuid id PK
        string name
        string email
        string phone
        string role "admin or therapist or customer"
        timestamp newsletter_opted_in_at
        timestamp email_verified_at
        timestamp deleted_at
    }
    client_profiles {
        uuid id PK
        uuid user_id FK
        date date_of_birth
        string address_city
        string occupation
        decimal weight
        decimal height
        string company_ico
        string company_dic
        text billing_address
        text anamnesis "nullable WYSIWYG"
    }
    therapist_profiles {
        uuid id PK
        uuid user_id FK
        text bio
        bool is_collaborator
        timestamp published_at
    }
    therapist_specializations {
        uuid id PK
        uuid therapist_id FK
        string name
        int display_order
    }
    therapy_records {
        uuid id PK
        uuid reservation_id FK
        uuid client_id FK
        uuid therapist_id FK
        text content
    }
    users ||--o| client_profiles : "has profile"
    users ||--o| therapist_profiles : "has profile"
    therapist_profiles ||--o{ therapist_specializations : "has"
    users ||--o{ therapy_records : "client"`,

clinic: `erDiagram
    users {
        uuid id PK
        string name
        string role
    }
    buildings {
        uuid id PK
        string name
        text address
    }
    rooms {
        uuid id PK
        uuid building_id FK
        string name
        int capacity
    }
    therapist_weekly_schedules {
        uuid id PK
        uuid therapist_id FK
        string day_of_week "mon..sun"
        string week_type "all or odd or even"
        time start_time
        time end_time
        uuid room_id FK
    }
    therapist_nonstandard_dates {
        uuid id PK
        uuid therapist_id FK
        date work_date
        time start_time
        time end_time
        uuid room_id FK
        string note
    }
    calendar_blocks {
        uuid id PK
        uuid therapist_id FK
        date start_date
        date end_date
        string reason
    }
    buildings ||--o{ rooms : "contains"
    users ||--o{ therapist_weekly_schedules : "therapist"
    rooms ||--o{ therapist_weekly_schedules : "room"
    users ||--o{ therapist_nonstandard_dates : "therapist"
    rooms ||--o{ therapist_nonstandard_dates : "room"
    users ||--o{ calendar_blocks : "therapist"`,

reserv: `erDiagram
    service_categories {
        uuid id PK
        string name
        string slug
        string type "nullable"
    }
    services {
        uuid id PK
        uuid category_id FK
        string name
        string slug
        string type "nullable"
        int duration_blocks "1 block = 15 min"
        int price "CZK integer"
        int break_blocks "blocks after service"
        string visibility "public or clients or invite or hidden"
        string custom_email_sender
        timestamp published_at
    }
    cancellation_rules {
        uuid id PK
        uuid service_id FK
        int cancel_before_hours
        int auto_cancel_after_days
    }
    service_rooms {
        uuid service_id FK
        uuid room_id FK
    }
    service_therapists {
        uuid service_id FK
        uuid therapist_id FK
    }
    reservations {
        uuid id PK
        uuid client_id FK
        uuid service_id FK
        uuid therapist_id FK
        uuid room_id FK
        date reservation_date
        time start_time
        time end_time
        string status "confirmed or pending or cancelled"
        string payment_status "unpaid or paid or overdue"
        bool is_control_therapy
        text notes
        timestamp deleted_at
    }
    invitations {
        uuid id PK
        string inviteable_type "polymorphic"
        uuid inviteable_id FK
        uuid invited_by FK
        uuid client_id FK "nullable"
        string email "nullable"
        string token "unique URL token"
        timestamp expires_at
        timestamp accepted_at
    }
    users {
        uuid id PK
        string name
        string role
    }
    rooms {
        uuid id PK
        string name
    }
    service_categories ||--o{ services : "groups"
    services ||--o| cancellation_rules : "has rules"
    services ||--o{ service_rooms : "held in"
    services ||--o{ service_therapists : "performed by"
    services ||--o{ invitations : "invite-only access"
    users ||--o{ invitations : "created by"
    users ||--o{ reservations : "client"
    services ||--o{ reservations : "type"
    users ||--o{ reservations : "therapist"
    rooms ||--o{ reservations : "room"`,

courses: `erDiagram
    course_categories {
        uuid id PK
        string name
        string slug
        text description
        timestamp published_at
        int display_order
    }
    courses {
        uuid id PK
        uuid category_id FK
        uuid instructor_id FK
        string name
        string slug
        int max_substitutions
        int early_cancel_hours
        timestamp published_at
    }
    course_series {
        uuid id PK
        uuid course_id FK
        string name
        date start_date
        date end_date
        int capacity
        int price "CZK integer"
        string status "open or full or inactive"
    }
    course_lessons {
        uuid id PK
        uuid series_id FK
        uuid instructor_id FK
        uuid room_id FK
        date lesson_date
        time start_time
        time end_time
    }
    one_time_lessons {
        uuid id PK
        uuid course_id FK
        uuid instructor_id FK
        uuid room_id FK
        date lesson_date
        time start_time
        int capacity
        int price "CZK integer"
    }
    course_enrollments {
        uuid id PK
        uuid client_id FK
        uuid series_id FK
        string status
        string payment_status
        timestamp paid_at
    }
    lesson_attendances {
        uuid id PK
        uuid enrollment_id FK
        uuid lesson_id FK
        bool attended
        timestamp cancelled_at
        bool token_generated
    }
    one_time_lesson_bookings {
        uuid id PK
        uuid client_id FK
        uuid lesson_id FK
        string status
        string payment_status
    }
    substitute_tokens {
        uuid id PK
        uuid client_id FK
        uuid source_lesson_id FK
        timestamp expires_at
        timestamp used_at
        uuid used_for_lesson_id FK
    }
    substitute_rules {
        uuid id PK
        uuid source_course_id FK
        uuid target_course_id FK
    }
    users {
        uuid id PK
        string name
    }
    course_categories ||--o{ courses : "groups"
    courses ||--o{ course_series : "has series"
    course_series ||--o{ course_lessons : "has lessons"
    courses ||--o{ one_time_lessons : "has one-time"
    users ||--o{ course_enrollments : "client"
    course_series ||--o{ course_enrollments : "enrolled in"
    course_enrollments ||--o{ lesson_attendances : "tracks"
    course_lessons ||--o{ lesson_attendances : "for lesson"
    users ||--o{ one_time_lesson_bookings : "client"
    one_time_lessons ||--o{ one_time_lesson_bookings : "booked"
    lesson_attendances ||--o| substitute_tokens : "generates"
    courses ||--o{ substitute_rules : "source"`,

workshops: `erDiagram
    workshops {
        uuid id PK
        uuid instructor_id FK
        uuid room_id FK
        string name
        string slug
        date workshop_date
        time start_time
        time end_time
        int capacity
        int price "CZK integer"
        timestamp published_at
        timestamp deleted_at
    }
    workshop_registrations {
        uuid id PK
        uuid client_id FK
        uuid workshop_id FK
        string status "confirmed or cancelled or waitlist"
        string payment_status
        timestamp paid_at
    }
    waitlist_entries {
        uuid id PK
        uuid client_id FK
        string waitlistable_type
        uuid waitlistable_id
        timestamp notified_at
        timestamp confirmed_at
    }
    reviews {
        uuid id PK
        uuid client_id FK
        string reviewable_type
        uuid reviewable_id
        text content
        string author_name
        bool visible
    }
    users {
        uuid id PK
        string name
    }
    rooms {
        uuid id PK
        string name
    }
    users ||--o{ workshops : "instructor"
    rooms ||--o{ workshops : "hosted in"
    workshops ||--o{ workshop_registrations : "has"
    users ||--o{ workshop_registrations : "client"
    users ||--o{ waitlist_entries : "waiting"
    users ||--o{ reviews : "author"`,

financial: `erDiagram
    invoice_series {
        uuid id PK
        string name
        string prefix "FT or KU etc"
        int current_number
        bool reset_yearly
        int last_reset_year
    }
    invoices {
        uuid id PK
        uuid series_id FK
        string invoice_number
        uuid client_id FK
        json client_snapshot
        int amount "CZK integer"
        string status "new or sent or paid or overdue"
        string payment_method "qr or cash or credit"
        date issued_at
        date due_at
        timestamp paid_at
    }
    cash_receipts {
        uuid id PK
        string receipt_number
        uuid invoice_id FK
        uuid client_id FK
        int amount "CZK integer"
        date received_at
    }
    payments {
        uuid id PK
        uuid client_id FK
        int amount "CZK integer"
        string method "qr or cash or credit"
        string variable_symbol
        string status "pending or matched or failed"
        uuid invoice_id FK
        timestamp paid_at
    }
    credit_accounts {
        uuid id PK
        uuid client_id FK
        int balance "CZK integer"
    }
    credit_transactions {
        uuid id PK
        uuid client_id FK
        int amount "CZK integer"
        string type "charge or deduct or expire"
        string description
        timestamp expires_at
    }
    gift_vouchers {
        uuid id PK
        string voucher_code
        int value "CZK integer"
        string recipient_name
        string recipient_email
        timestamp purchased_at
        timestamp expires_at
        timestamp redeemed_at
        uuid credited_to_client_id FK
    }
    users {
        uuid id PK
        string name
    }
    invoice_series ||--o{ invoices : "numbers"
    users ||--o{ invoices : "issued to"
    invoices ||--o| cash_receipts : "has receipt"
    users ||--o{ payments : "made by"
    invoices ||--o{ payments : "settled by"
    users ||--|| credit_accounts : "has"
    users ||--o{ credit_transactions : "has"
    gift_vouchers }o--o| users : "credited to"`,

cms: `erDiagram
    pages {
        uuid id PK
        string slug
        string title
        string meta_title
        text meta_description
        bool is_system
        uuid created_by FK
        timestamp published_at
        timestamp deleted_at
    }
    page_blocks {
        uuid id PK
        uuid page_id FK
        string type
        int display_order
        bool visible
        json settings
    }
    navigations {
        uuid id PK
        string location "header or footer"
    }
    navigation_items {
        uuid id PK
        uuid navigation_id FK
        uuid parent_id FK
        string label
        string url
        int display_order
    }
    banners {
        uuid id PK
        string title
        string link_url
        bool visible
        timestamp starts_at
        timestamp ends_at
    }
    modals {
        uuid id PK
        string title
        text content
        string trigger "timer or exit_intent"
        string frequency "once or daily or always"
        bool visible
        timestamp starts_at
        timestamp ends_at
    }
    top_bars {
        uuid id PK
        text content
        string link_url
        string background_color
        bool visible
    }
    email_templates {
        uuid id PK
        string event_type
        string subject
        text body_html
        string sender_email
        string service_type
    }
    users {
        uuid id PK
        string name
    }
    pages ||--o{ page_blocks : "has blocks"
    navigations ||--o{ navigation_items : "has items"
    navigation_items ||--o{ navigation_items : "children"`
};

function show(key) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.getElementById('t-' + key).classList.add('active');
    document.getElementById('p-' + key).classList.add('active');
    renderIfNeeded(key);
}

const rendered = new Set();

async function renderIfNeeded(key) {
    if (rendered.has(key)) return;
    rendered.add(key);
    const el = document.getElementById('d-' + key);
    if (!el || !DIAGRAMS[key]) return;
    try {
        const { svg } = await mermaid.render('svg-' + key, DIAGRAMS[key]);
        el.innerHTML = svg;
    } catch (e) {
        el.innerHTML = '<span style="color:#f87171;font-size:12px;">Render error: ' + e.message + '</span>';
    }
}

mermaid.initialize({
    startOnLoad: false,
    theme: 'dark',
    themeVariables: {
        primaryColor: '#1e1e2e',
        primaryTextColor: '#c9ccd6',
        primaryBorderColor: '#ED86A3',
        lineColor: '#ED86A3',
        secondaryColor: '#181820',
        tertiaryColor: '#111118',
        edgeLabelBackground: '#181820',
        attributeBackgroundColorEven: '#13131c',
        attributeBackgroundColorOdd: '#181820',
        fontFamily: '-apple-system, Inter, sans-serif',
        fontSize: '13px',
    },
    er: {
        diagramPadding: 20,
        layoutDirection: 'TB',
        minEntityWidth: 110,
        minEntityHeight: 50,
        entityPadding: 12,
        useMaxWidth: true,
    }
});

renderIfNeeded('overview');
</script>
</body>
</html>
