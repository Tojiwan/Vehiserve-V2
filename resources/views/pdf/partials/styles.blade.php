/* Dompdf-friendly styles: tables and static blocks only, no flex/grid. */
@page { margin: 28mm 18mm 22mm 18mm; }

body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #1c1c1c; line-height: 1.45; }

.header { width: 100%; border-bottom: 2px solid #7a1f1f; padding-bottom: 10px; margin-bottom: 16px; }
.header td { vertical-align: middle; }
.logo { width: 54px; }
.org { font-size: 13pt; font-weight: bold; letter-spacing: 0.5px; }
.org-sub { font-size: 8.5pt; color: #555; }

.title { font-size: 15pt; font-weight: bold; margin: 0 0 2px 0; }
.subtitle { font-size: 9.5pt; color: #555; margin: 0 0 14px 0; }

.notice { border: 1px solid #7a1f1f; background: #f7ecec; color: #7a1f1f; font-weight: bold; padding: 7px 10px; margin-bottom: 14px; text-align: center; letter-spacing: 0.5px; }
.notice-plain { border: 1px solid #999; background: #f4f4f4; color: #444; padding: 6px 10px; margin-bottom: 14px; font-size: 9pt; text-align: center; }

h2 { font-size: 11.5pt; margin: 18px 0 6px 0; border-bottom: 1px solid #bbb; padding-bottom: 3px; }

table.data { width: 100%; border-collapse: collapse; }
table.data th, table.data td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; vertical-align: top; }
table.data th { background: #f0f0f0; font-size: 9pt; }
table.data td.label { width: 32%; background: #fafafa; font-weight: bold; }

p { margin: 6px 0; }
p.note { font-size: 8.5pt; color: #555; margin-top: 6px; }
.footer { margin-top: 22px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 8pt; color: #666; }
