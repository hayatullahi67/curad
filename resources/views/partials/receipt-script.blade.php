{{--
    The one receipt CURA prints. Built as its own small document so that the
    on-screen preview, the printout, the saved file and the WhatsApp text all
    match, and so printing is sized for an 80mm thermal roll with no browser
    headers or footers. Include once per page; use window.CuraReceipt.
--}}
<style>
    /* The on-screen preview: a strip of till roll with a torn bottom edge. */
    .receipt-paper { position: relative; margin-bottom: 9px; }
    .receipt-paper::after {
        content: ''; position: absolute; left: 0; right: 0; bottom: -8px; height: 8px;
        background:
            linear-gradient(-45deg, transparent 5.5px, #fff 0) 0 0 / 11px 8px repeat-x,
            linear-gradient(45deg, transparent 5.5px, #fff 0) 0 0 / 11px 8px repeat-x;
    }
</style>
<script>
window.CuraReceipt = (() => {
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const money = (amount) => '₦' + Number(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const quantity = (amount) => Number(amount || 0).toLocaleString('en-NG');

    // "2026-10-03 16:12:40" -> "3 Oct 2026, 4:12 PM"
    function when(text) {
        const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(text || ''));
        if (!match) return text || '';
        const date = new Date(+match[1], +match[2] - 1, +match[3], +match[4], +match[5]);
        return date.toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })
            + ', ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function paymentsOf(rcpt) {
        return rcpt.payments && rcpt.payments.length
            ? rcpt.payments
            : [{ label: rcpt.payment_method || 'Cash', tendered: rcpt.amount_tendered ?? rcpt.net_total, reference: '' }];
    }

    // The receipt fills whatever paper the printer uses: the full width of an
    // A4 sheet, or the full width of a thermal roll. Text grows with the
    // paper (vw is the page width when printing), so it is never tiny.
    // margin: 0 also stops the browser printing its date and web address.
    // Thermal printers print only black: hierarchy comes from size and rules,
    // with medium weights so nothing looks heavy.
    const css = `
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .receipt {
            width: 100%; margin: 0 auto;
            padding: max(5mm, 4vw) max(4mm, 6vw) max(6mm, 4.5vw);
            color: #000; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            font-size: clamp(12px, 2.45vw, 19px); line-height: 1.45; font-weight: 400;
            font-variant-numeric: tabular-nums;
        }
        .center { text-align: center; }
        .shop { font-size: 1.55em; font-weight: 700; line-height: 1.2; letter-spacing: 0.02em; text-transform: uppercase; }
        .small { font-size: 0.88em; }
        .strong { font-weight: 600; }
        .rule { border: 0; border-top: 1px dashed #000; margin: 0.8em 0; }
        .rule.solid { border-top-style: solid; }
        .rule.double { border-top: 3px double #000; margin: 0.6em 0; }
        .label { font-size: 0.95em; font-weight: 600; letter-spacing: 0.3em; text-transform: uppercase; }
        .row { display: flex; justify-content: space-between; align-items: baseline; gap: 1em; }
        .row > :last-child { text-align: right; white-space: nowrap; }
        .meta .row > :first-child { flex-shrink: 0; }
        .meta .row > :last-child { white-space: normal; word-break: break-word; }
        .items { margin: 0; padding: 0; list-style: none; }
        .item { padding: 0.35em 0; }
        .item + .item { border-top: 1px dotted #000; }
        .item, .row, .copy { break-inside: avoid; }
        .item .name { font-weight: 600; word-break: break-word; }
        .total { font-size: 1.4em; font-weight: 700; }
        .copy { border: 1.5px solid #000; padding: 0.45em 0.4em; margin-bottom: 0.9em; text-align: center; }
        .copy .word { font-size: 1.15em; font-weight: 700; letter-spacing: 0.35em; }
        .thanks { font-size: 1.08em; font-weight: 600; }
        /* A saved receipt opened in a wide browser window stays receipt-shaped. */
        @media screen and (min-width: 560px) {
            .receipt { max-width: 440px; font-size: 15px; padding: 32px 28px 36px; }
        }
    `;

    function body(rcpt, options = {}) {
        const shop = rcpt.pharmacy || {};
        const items = rcpt.items || [];
        const units = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        const status = rcpt.status && rcpt.status !== 'completed' ? (rcpt.status_label || rcpt.status) : '';

        const copyBlock = options.copy ? `
            <div class="copy">
                <div class="word">COPY</div>
                <div class="small">Reprinted ${esc(when(options.reprintedAt || ''))}${status ? ` · ${esc(status)}` : ''}</div>
            </div>` : (status ? `<div class="copy"><div class="word">${esc(status.toUpperCase())}</div></div>` : '');

        const shopLines = [
            shop.address ? `<div class="small">${esc(shop.address)}</div>` : '',
            shop.phone ? `<div class="small">Tel: ${esc(shop.phone)}</div>` : '',
        ].join('');

        const metaRow = (label, value) => value ? `<div class="row"><span>${label}</span><span>${esc(value)}</span></div>` : '';

        const itemRows = items.map((item) => {
            const qty = Number(item.quantity || 0);
            const unit = item.unit_price != null ? Number(item.unit_price) : (qty ? Number(item.total) / qty : Number(item.total));
            return `
                <li class="item">
                    <div class="name">${esc(item.name)}</div>
                    <div class="row"><span>${quantity(qty)} × ${money(unit)}</span><span class="strong">${money(item.total)}</span></div>
                    ${item.batch_number ? `<div class="small">Batch ${esc(item.batch_number)}</div>` : ''}
                </li>`;
        }).join('');

        const paymentRows = paymentsOf(rcpt).map((line) => `
            <div class="row"><span>${esc(line.label)}${line.reference ? ` <span class="small">(${esc(line.reference)})</span>` : ''}</span><span>${money(line.tendered)}</span></div>`).join('');

        return `
            <div class="receipt">
                ${copyBlock}
                <div class="center">
                    <div class="shop">${esc(shop.name || 'Pharmacy')}</div>
                    ${shopLines}
                </div>
                <hr class="rule">
                <div class="center label">Sales Receipt</div>
                <hr class="rule">
                <div class="meta">
                    ${metaRow('Receipt No.', rcpt.receipt_no)}
                    ${metaRow('Date', when(rcpt.created_at))}
                    ${metaRow('Cashier', rcpt.cashier && rcpt.cashier.name)}
                    ${metaRow('Customer', (rcpt.customer && rcpt.customer.name) || 'Walk-in Customer')}
                    ${metaRow('Phone', rcpt.customer && rcpt.customer.phone)}
                </div>
                <hr class="rule solid">
                <ul class="items">${itemRows}</ul>
                <hr class="rule solid">
                <div class="row"><span>Subtotal</span><span>${money(rcpt.subtotal)}</span></div>
                ${Number(rcpt.discount) > 0 ? `<div class="row"><span>Discount</span><span>−${money(rcpt.discount)}</span></div>` : ''}
                <hr class="rule double">
                <div class="row total"><span>TOTAL</span><span>${money(rcpt.net_total)}</span></div>
                <hr class="rule double">
                ${paymentRows}
                <div class="row"><span>Change</span><span>${money(rcpt.change_due)}</span></div>
                ${Number(rcpt.refunded_amount) > 0 ? `<div class="row strong"><span>Refunded</span><span>−${money(rcpt.refunded_amount)}</span></div>` : ''}
                <hr class="rule">
                <div class="row small"><span>${items.length} ${items.length === 1 ? 'product' : 'products'}</span><span>${quantity(units)} ${units === 1 ? 'unit' : 'units'}</span></div>
                <hr class="rule">
                <div class="center">
                    <div class="thanks">Thank you for your patronage!</div>
                    <div class="small">Please keep this receipt for your records.</div>
                    <div class="small" style="margin-top:6px">Powered by CuraD</div>
                </div>
            </div>`;
    }

    // A complete page: what is previewed, printed and saved.
    function documentHtml(rcpt, options = {}) {
        return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Receipt ${esc(rcpt.receipt_no)}</title><style>${css}</style></head><body>${body(rcpt, options)}</body></html>`;
    }

    // Shows the receipt in an iframe, sized to its content.
    function preview(frame, rcpt, options = {}) {
        frame.onload = () => {
            try { frame.style.height = frame.contentDocument.documentElement.scrollHeight + 'px'; } catch (e) { /* ignore */ }
        };
        frame.srcdoc = documentHtml(rcpt, options);
    }

    // Prints any document from a hidden frame, so the page itself is never printed.
    function printHtml(html) {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:80mm;height:20px;border:0;';
        document.body.appendChild(frame);
        frame.onload = () => {
            setTimeout(() => {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(() => frame.remove(), 1500);
            }, 50);
        };
        frame.srcdoc = html;
    }

    function print(rcpt, options = {}) {
        printHtml(documentHtml(rcpt, options));
    }

    // Saves the same receipt as a file that opens (and prints) in any browser.
    function download(rcpt, options = {}) {
        const blob = new Blob([documentHtml(rcpt, options)], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = Object.assign(document.createElement('a'), { href: url, download: `${String(rcpt.receipt_no).replace(/[^a-z0-9-_]/gi, '-')}-receipt.html` });
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    // Plain text for WhatsApp.
    function text(rcpt, options = {}) {
        const shop = rcpt.pharmacy || {};
        return [
            ...(options.copy ? ['*RECEIPT COPY*'] : []),
            `*${String(shop.name || 'Pharmacy').toUpperCase()}*`,
            `Receipt: ${rcpt.receipt_no}`,
            `Date: ${when(rcpt.created_at)}`,
            `Customer: ${(rcpt.customer && rcpt.customer.name) || 'Walk-in Customer'}`,
            '',
            ...(rcpt.items || []).map((item) => `${quantity(item.quantity)} × ${item.name}: ${money(item.total)}`),
            '',
            `Subtotal: ${money(rcpt.subtotal)}`,
            ...(Number(rcpt.discount) > 0 ? [`Discount: −${money(rcpt.discount)}`] : []),
            `*TOTAL: ${money(rcpt.net_total)}*`,
            ...paymentsOf(rcpt).map((line) => `Paid by ${line.label}: ${money(line.tendered)}`),
            `Change: ${money(rcpt.change_due)}`,
            '',
            'Thank you for your patronage!',
            'Powered by CuraD',
        ].join('\n');
    }

    return { documentHtml, preview, print, printHtml, download, text, when };
})();
</script>
