@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Billing POS</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('pos.online_orders.index') }}" class="btn btn-sm btn-outline-secondary">Online Queue</a>
        <a href="{{ route('pos.sales.index') }}" class="btn btn-sm btn-outline-primary">Invoice History</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">
        {{ session('success') }}.
        @if(session('invoice_sale_id'))
            <button
                type="button"
                class="btn btn-link alert-link p-0 align-baseline js-invoice-preview"
                data-invoice-url="{{ route('pos.sales.invoice', ['sale' => session('invoice_sale_id'), 'embed' => 1]) }}"
                data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => session('invoice_sale_id')]) }}"
            >Open Invoice</button>
            |
            <a href="{{ route('pos.sales.invoice.pdf', ['sale' => session('invoice_sale_id')]) }}" class="alert-link">Download PDF</a>
        @endif
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning py-2">{{ session('warning') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@php($gstEnabled = (bool) ($businessSettings['gst_enabled'] ?? false))

<form method="POST" action="{{ route('pos.checkout') }}" id="pos-form">
    @csrf
    <input type="hidden" name="customer_id" id="customer_id_input" value="{{ old('customer_id') }}">
    <input type="hidden" name="submit_action" id="submit_action_input" value="{{ old('submit_action', 'invoice') }}">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Add Item by Product Code</div>
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-1">
                                <span>Product Code</span>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary py-0 px-2"
                                    data-bs-toggle="popover"
                                    data-bs-trigger="focus"
                                    data-bs-placement="right"
                                    data-bs-content="Only active finished goods can be added. Search by FG code, legacy code, or product name."
                                    title="POS Product Help"
                                >i</button>
                            </label>
                            <div class="position-relative">
                                <input id="product-code-input" class="form-control" autocomplete="off" placeholder="FG001 / 02 / Product name">
                                <div id="product-suggestions" class="list-group position-absolute w-100 shadow-sm" style="z-index:20;max-height:240px;overflow-y:auto;display:none;"></div>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="button" id="add-by-code-btn" class="btn btn-outline-primary">Add</button>
                        </div>
                        <div class="col-md-4">
                            <div id="lookup-message" class="small mt-1"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Cart Items</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead><tr><th>Code</th><th>Item</th><th>Price</th><th>Stock</th><th>Qty</th><th>Total</th><th></th></tr></thead>
                            <tbody id="cart-body">
                                <tr id="empty-cart-row"><td colspan="7" class="text-center text-muted py-3">No items added yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="items-hidden-inputs"></div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3 sticky-top" style="top:10px;">
                <div class="card-header">Order Context</div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <input type="hidden" name="order_reference" id="order_reference" value="{{ old('order_reference') }}">
                        <div class="col-12">
                            <label class="form-label">Source</label>
                            <select name="order_source" id="order_source" class="form-select">
                                @foreach($orderSources as $source)
                                    <option value="{{ $source }}" {{ old('order_source', 'outlet') === $source ? 'selected' : '' }}>
                                        {{ $source === 'other' ? 'OTHERS' : strtoupper($source) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Customer Mobile</label>
                            <input
                                type="text"
                                name="customer_identifier"
                                id="customer_identifier_input"
                                class="form-control"
                                value="{{ old('customer_identifier') }}"
                                maxlength="10"
                                pattern="\d{10}"
                                inputmode="numeric"
                                placeholder="10-digit mobile"
                            >
                        </div>
                        <div class="col-4 d-grid">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" id="customer_lookup_btn" class="btn btn-outline-secondary">Check</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" id="customer_name_input" class="form-control" value="{{ old('customer_name') }}">
                        </div>
                        <div class="col-12 d-none">
                            <input type="hidden" name="customer_address" id="customer_address_input" value="{{ old('customer_address') }}">
                        </div>
                        <div class="col-12" id="customer_address_group">
                            <div class="row g-2" id="customer_address_fields">
                                <div class="col-12">
                                    <label class="form-label">Apartment / House</label>
                                    <input type="text" name="customer_address_line1" id="customer_address_line1_input" class="form-control" value="{{ old('customer_address_line1') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Road</label>
                                    <input type="text" name="customer_road" id="customer_road_input" class="form-control" value="{{ old('customer_road') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sector</label>
                                    <input type="text" name="customer_sector" id="customer_sector_input" class="form-control" value="{{ old('customer_sector') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="customer_city" id="customer_city_input" class="form-control" value="{{ old('customer_city') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" name="customer_pincode" id="customer_pincode_input" class="form-control" value="{{ old('customer_pincode') }}" maxlength="6" pattern="\d{6}" inputmode="numeric">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Preference</label>
                                    <input type="text" name="customer_preference" id="customer_preference_input" class="form-control" value="{{ old('customer_preference') }}" placeholder="Eggless, less sugar, favorite flavor, etc.">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="customer_lookup_result" class="small text-muted mb-2"></div>
                </div>
            </div>

            <div class="card sticky-top" style="top: 420px;">
                <div class="card-header">Payment and Summary</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-select">
                            <option value="">Select payment mode</option>
                            <option value="cash" {{ old('payment_mode') === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="upi" {{ old('payment_mode') === 'upi' ? 'selected' : '' }}>UPI</option>
                            <option value="card" {{ old('payment_mode') === 'card' ? 'selected' : '' }}>Card</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">Discount</label><input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount" value="{{ old('discount_amount', '0') }}" class="form-control"></div>
                        <div class="col-6" id="tax-input-group" style="{{ $gstEnabled ? '' : 'display:none;' }}">
                            <label class="form-label">Tax (GST)</label>
                            <input type="number" step="0.01" min="0" id="tax_amount" name="tax_amount" value="{{ old('tax_amount', '0') }}" class="form-control">
                        </div>
                        <div class="col-6"><label class="form-label">Round Off</label><input type="number" step="0.01" id="round_off" name="round_off" value="{{ old('round_off', '0') }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Paid</label><input type="number" step="0.01" min="0" id="paid_amount" name="paid_amount" value="{{ old('paid_amount') }}" class="form-control"></div>
                    </div>
                    <div id="paid_help" class="small mt-1 text-muted">Leave empty to auto-fill full payment.</div>

                    <h6 class="mt-3">Bill Summary</h6>
                    <table class="table table-sm mb-2">
                        <tr><th>Subtotal</th><td class="text-end">Rs.<span id="sub-total">0.00</span></td></tr>
                        <tr><th>Discount</th><td class="text-end">Rs.<span id="summary-discount">0.00</span></td></tr>
                        <tr id="summary-tax-row" style="{{ $gstEnabled ? '' : 'display:none;' }}"><th>Tax (GST)</th><td class="text-end">Rs.<span id="summary-tax">0.00</span></td></tr>
                        <tr><th>Round Off</th><td class="text-end">Rs.<span id="summary-round-off">0.00</span></td></tr>
                        <tr class="table-light"><th>Total</th><td class="text-end"><strong>Rs.<span id="grand-total">0.00</span></strong></td></tr>
                        <tr><th>Paid</th><td class="text-end">Rs.<span id="summary-paid">0.00</span></td></tr>
                        <tr><th>Balance</th><td class="text-end"><span id="summary-balance" class="fw-bold">0.00</span></td></tr>
                    </table>
                    <div id="cart_help" class="small text-muted mb-2">Add at least one item to enable checkout.</div>
                    <div class="d-grid"><button type="button" id="checkout-btn" class="btn btn-primary">Review and Create Invoice</button></div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="invoice-review-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Invoice Summary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-md-6"><strong>Source:</strong> <span id="review-order-source">-</span></div>
                    <div class="col-md-6"><strong>Customer:</strong> <span id="review-customer">Walk-in / Unknown</span></div>
                </div>
                <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Code</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead><tbody id="review-items-body"></tbody></table></div>
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal</th><td class="text-end">Rs.<span id="review-subtotal">0.00</span></td></tr>
                    <tr><th>Discount</th><td class="text-end">Rs.<span id="review-discount">0.00</span></td></tr>
                    <tr id="review-tax-row" style="{{ $gstEnabled ? '' : 'display:none;' }}"><th>Tax (GST)</th><td class="text-end">Rs.<span id="review-tax">0.00</span></td></tr>
                    <tr><th>Round Off</th><td class="text-end">Rs.<span id="review-roundoff">0.00</span></td></tr>
                    <tr class="table-light"><th>Total</th><td class="text-end"><strong>Rs.<span id="review-total">0.00</span></strong></td></tr>
                    <tr><th>Paid</th><td class="text-end">Rs.<span id="review-paid">0.00</span></td></tr>
                    <tr><th>Balance</th><td class="text-end">Rs.<span id="review-balance">0.00</span></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to Edit</button>
                <button type="button" class="btn btn-primary" id="confirm-create-invoice-btn">Confirm and Create Invoice</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="repeat-customer-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repeat-customer-title">Customer Lookup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="repeat-customer-summary" class="mb-2"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Previous Orders</h6>
                        <ul id="repeat-customer-orders" class="mb-0 small"></ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Favorite Items</h6>
                        <ul id="repeat-customer-favorites" class="mb-0 small"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invoice-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="invoice-preview-frame" src="about:blank" style="width:100%;height:75vh;border:0;"></iframe>
            </div>
            <div class="modal-footer">
                <a href="#" id="invoice-preview-pdf" class="btn btn-success">Download PDF</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function(){
const form=document.getElementById('pos-form');if(!form)return;
const gstEnabled=@json($gstEnabled);
const searchUrl=@json(route('pos.products.search')),lookupUrl=@json(route('pos.products.lookup')),customerLookupUrl=@json(route('pos.customers.lookup')),initialItems=@json($initialItems??[]);
const codeInput=document.getElementById('product-code-input'),addBtn=document.getElementById('add-by-code-btn'),suggEl=document.getElementById('product-suggestions'),msgEl=document.getElementById('lookup-message');
const cartBody=document.getElementById('cart-body'),emptyRow=document.getElementById('empty-cart-row'),hiddenWrap=document.getElementById('items-hidden-inputs'),cartHelp=document.getElementById('cart_help');
const customerId=document.getElementById('customer_id_input'),customerIdentifier=document.getElementById('customer_identifier_input'),customerLookupBtn=document.getElementById('customer_lookup_btn'),customerResult=document.getElementById('customer_lookup_result'),customerName=document.getElementById('customer_name_input'),customerAddress=document.getElementById('customer_address_input'),customerAddressGroup=document.getElementById('customer_address_group'),customerAddressLine1=document.getElementById('customer_address_line1_input'),customerRoad=document.getElementById('customer_road_input'),customerSector=document.getElementById('customer_sector_input'),customerCity=document.getElementById('customer_city_input'),customerPincode=document.getElementById('customer_pincode_input'),customerPreference=document.getElementById('customer_preference_input');
const orderSource=document.getElementById('order_source');
const discount=document.getElementById('discount_amount'),tax=document.getElementById('tax_amount'),roundOff=document.getElementById('round_off'),paid=document.getElementById('paid_amount'),paidHelp=document.getElementById('paid_help'),checkoutBtn=document.getElementById('checkout-btn'),submitActionInput=document.getElementById('submit_action_input');
const subTotalEl=document.getElementById('sub-total'),grandEl=document.getElementById('grand-total'),discEl=document.getElementById('summary-discount'),taxEl=document.getElementById('summary-tax'),roundEl=document.getElementById('summary-round-off'),paidEl=document.getElementById('summary-paid'),balanceEl=document.getElementById('summary-balance');
const taxInputGroup=document.getElementById('tax-input-group'),summaryTaxRow=document.getElementById('summary-tax-row'),reviewTaxRow=document.getElementById('review-tax-row');
const modalEl=document.getElementById('invoice-review-modal'),modal=modalEl&&window.bootstrap?new window.bootstrap.Modal(modalEl):null,reviewItems=document.getElementById('review-items-body');
const rSource=document.getElementById('review-order-source'),rCustomer=document.getElementById('review-customer'),rSub=document.getElementById('review-subtotal'),rDisc=document.getElementById('review-discount'),rTax=document.getElementById('review-tax'),rRound=document.getElementById('review-roundoff'),rTotal=document.getElementById('review-total'),rPaid=document.getElementById('review-paid'),rBalance=document.getElementById('review-balance');
const confirmBtn=document.getElementById('confirm-create-invoice-btn');
const repeatModalEl=document.getElementById('repeat-customer-modal'),repeatModal=repeatModalEl&&window.bootstrap?new window.bootstrap.Modal(repeatModalEl):null,repeatTitle=document.getElementById('repeat-customer-title'),repeatSummary=document.getElementById('repeat-customer-summary'),repeatOrders=document.getElementById('repeat-customer-orders'),repeatFavorites=document.getElementById('repeat-customer-favorites');
const invoicePreviewModalEl=document.getElementById('invoice-preview-modal'),invoicePreviewModal=invoicePreviewModalEl&&window.bootstrap?new window.bootstrap.Modal(invoicePreviewModalEl):null,invoicePreviewFrame=document.getElementById('invoice-preview-frame'),invoicePreviewPdf=document.getElementById('invoice-preview-pdf');
if(window.bootstrap){document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el)=>new window.bootstrap.Popover(el));}
const cart=new Map();let suggestions=[],highlight=-1,searchTimer=null,confirmed=false,lastRepeatPopupKey='',lastCustomerPayload=null,lastCustomerIdentifier='';
if(submitActionInput)submitActionInput.value='invoice';
const num=(v)=>{const n=parseFloat(v);return Number.isFinite(n)?n:0;};
const esc=(v)=>String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
const codeLabel=(item)=>String(item?.display_code||((item?.legacy_code&&String(item.legacy_code).trim()!=='')?`${item.code} | ${item.legacy_code}`:(item?.code||'')));
const normCode=(v)=>(v||'').trim().toUpperCase();
const normId=(v)=>{const r=(v||'').trim();if(!r)return'';const d=r.replace(/\D/g,'');if(d.length>=10)return d.slice(-10);if(d.length>0)return d;return r.toUpperCase();};
const activeItems=()=>Array.from(cart.values()).filter(i=>i.quantity>0);
const setMsg=(m,t='ok')=>{msgEl.textContent=m||'';msgEl.className=!m?'small mt-1':(t==='error'?'small mt-1 text-danger':'small mt-1 text-success');};
const setCustomerHtml=(h,t='ok')=>{customerResult.innerHTML=h||'';customerResult.className=t==='error'?'small text-danger mb-2':(t==='repeat'?'small text-warning mb-2':'small text-muted mb-2');};
const clearSuggestions=()=>{suggestions=[];highlight=-1;suggEl.innerHTML='';suggEl.style.display='none';};
const isPhoneOrWhatsappSource=()=>{const src=(orderSource.value||'outlet').toLowerCase();return src==='phone'||src==='whatsapp';};
const addressInputs=[customerAddressLine1,customerRoad,customerSector,customerCity,customerPincode];
function composeAddress(){const parts=[customerAddressLine1?.value||'',customerRoad?.value||'',customerSector?.value||'',customerCity?.value||''].map(v=>String(v).trim()).filter(v=>v!=='');let out=parts.join(', ');const pin=(customerPincode?.value||'').replace(/\D/g,'').slice(0,6);if(customerPincode)customerPincode.value=pin;if(pin)out=(out?`${out} - ${pin}`:pin);return out;}
function syncHiddenAddress(){if(customerAddress)customerAddress.value=composeAddress();}
function setAddressFields(profile={},overwrite=true){const map=[['address_line1',customerAddressLine1],['road',customerRoad],['sector',customerSector],['city',customerCity],['pincode',customerPincode],['preference',customerPreference]];map.forEach(([k,input])=>{if(!input)return;const v=String(profile?.[k]??'').trim();if(!v)return;if(overwrite||!String(input.value||'').trim())input.value=v;});syncHiddenAddress();}
function applyOrderSourceRules(){const src=(orderSource.value||'outlet').toLowerCase();const required=src!=='outlet';const showAddress=required;if(customerIdentifier)customerIdentifier.required=required;if(customerName)customerName.required=required;[customerAddressLine1,customerCity,customerPincode].forEach((input)=>{if(input)input.required=required&&showAddress;});if(customerAddressGroup)customerAddressGroup.style.display=showAddress?'':'none';syncHiddenAddress();}
function applyGstRules(){if(taxInputGroup)taxInputGroup.style.display=gstEnabled?'':'none';if(summaryTaxRow)summaryTaxRow.style.display=gstEnabled?'':'none';if(reviewTaxRow)reviewTaxRow.style.display=gstEnabled?'':'none';if(tax){if(!gstEnabled){tax.value='0';tax.readOnly=true;}else{tax.readOnly=false;}}}
function applyCustomerAutofill(payload){if(!payload)return;const stats=payload?.stats||{},c=payload?.customer||null;const matchedName=((c&&c.name)||stats?.last_sale?.customer_name||'').trim();if(isPhoneOrWhatsappSource()){if(customerName)customerName.value=matchedName;}else if(customerName&&!customerName.value.trim()){customerName.value=matchedName;}setAddressFields({address_line1:c?.address_line1||'',road:c?.road||'',sector:c?.sector||'',city:c?.city||'',pincode:c?.pincode||'',preference:c?.preference||''},isPhoneOrWhatsappSource());}
function renderSuggestions(){suggEl.innerHTML='';if(!suggestions.length){suggEl.style.display='none';return;}suggestions.forEach((it,idx)=>{const b=document.createElement('button');b.type='button';b.className='list-group-item list-group-item-action py-2'+(idx===highlight?' active':'');b.innerHTML=`<div class="d-flex justify-content-between"><div><strong>${esc(codeLabel(it))}</strong> - ${esc(it.name)}</div><small>Rs.${num(it.price).toFixed(2)}</small></div>`;b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',()=>addProduct(it));suggEl.appendChild(b);});suggEl.style.display='block';}
function rebuildHidden(){hiddenWrap.innerHTML='';let i=0;activeItems().forEach(it=>{const p=document.createElement('input');p.type='hidden';p.name=`items[${i}][product_id]`;p.value=String(it.id);const q=document.createElement('input');q.type='hidden';q.name=`items[${i}][quantity]`;q.value=String(it.quantity);hiddenWrap.appendChild(p);hiddenWrap.appendChild(q);i++;});syncHiddenAddress();}
function summary(){let sub=0;cart.forEach(it=>sub+=it.quantity*it.price);const d=num(discount.value),tx=gstEnabled?num(tax&&tax.value):0,ro=num(roundOff.value),total=Math.max(0,sub-d+tx+ro);const paidRaw=(paid.value||'').trim(),pd=paidRaw===''?total:num(paidRaw),bal=total-pd;return{sub,d,tx,ro,total,pd,bal};}
function recalc(){const s=summary(),has=activeItems().length>0;if(s.bal<0){paid.setCustomValidity('Paid amount cannot exceed final total.');paidHelp.textContent='Paid amount cannot exceed final total.';paidHelp.className='small mt-1 text-danger';}else{paid.setCustomValidity('');paidHelp.textContent='Leave empty to auto-fill full payment.';paidHelp.className='small mt-1 text-muted';}
if(checkoutBtn)checkoutBtn.disabled=!has||s.bal<0;cartHelp.className=!has?'small text-muted mb-2':(s.bal<0?'small text-danger mb-2':'small mb-2');cartHelp.textContent=!has?'Add at least one item to enable checkout.':(s.bal<0?'Paid amount cannot exceed final total.':'');
subTotalEl.textContent=s.sub.toFixed(2);discEl.textContent=s.d.toFixed(2);taxEl.textContent=s.tx.toFixed(2);roundEl.textContent=s.ro.toFixed(2);grandEl.textContent=s.total.toFixed(2);paidEl.textContent=s.pd.toFixed(2);balanceEl.textContent=s.bal.toFixed(2);balanceEl.className=s.bal>0?'fw-bold text-danger':'fw-bold text-success';rebuildHidden();}
function renderCart(){Array.from(cartBody.querySelectorAll('tr[data-product-id]')).forEach(r=>r.remove());if(!cart.size){emptyRow.classList.remove('d-none');recalc();return;}emptyRow.classList.add('d-none');
cart.forEach(it=>{const tr=document.createElement('tr');tr.dataset.productId=String(it.id);tr.innerHTML=`<td><strong>${esc(it.code||'-')}</strong></td><td><strong>${esc(it.name)}</strong><div class="small text-muted">Unit: ${esc(it.unit)}</div></td><td>Rs.${it.price.toFixed(2)}</td><td>${it.stock.toFixed(2)} ${esc(it.unit)}</td><td><input type="number" min="0" step="1" class="form-control form-control-sm cart-qty-input" value="${it.quantity}" data-product-id="${it.id}"></td><td>Rs.<span class="line-total">${(it.quantity*it.price).toFixed(2)}</span></td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-product-id="${it.id}">x</button></td>`;cartBody.appendChild(tr);});recalc();}
function upsert(it,addQty){const k=String(it.id);if(cart.has(k)){const e=cart.get(k);e.quantity+=addQty;e.stock=it.stock;e.price=it.price;e.code=codeLabel(it);e.name=it.name;e.unit=it.unit;cart.set(k,e);}else{cart.set(k,{id:Number(it.id),code:codeLabel(it),name:String(it.name||''),unit:String(it.unit||'pcs'),price:num(it.price),stock:num(it.stock),quantity:addQty});}}
function addProduct(it){upsert(it,1);renderCart();setMsg(`${codeLabel(it)} - ${it.name} added.`);clearSuggestions();codeInput.value='';codeInput.focus();}
async function searchProducts(q){const s=(q||'').trim();if(!s){clearSuggestions();return;}try{const r=await fetch(`${searchUrl}?q=${encodeURIComponent(s)}`,{headers:{'Accept':'application/json'}});const p=await r.json();if(!r.ok||!Array.isArray(p)){clearSuggestions();return;}suggestions=p;highlight=p.length?0:-1;renderSuggestions();}catch{clearSuggestions();}}
async function addByCode(){const code=normCode(codeInput.value);if(!code){setMsg('Enter product code or select suggestion.','error');codeInput.focus();return;}if(!/^\d{1,3}$/.test(code)&&!/^(FG|RM|PK)\d{1,3}$/i.test(code)){setMsg('Use FG001 or short code like 001/01.','error');codeInput.focus();return;}
addBtn.disabled=true;setMsg('Searching...');try{const r=await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`,{headers:{'Accept':'application/json'}});const p=await r.json();if(!r.ok)throw new Error((p&&p.message)||'Product not found.');addProduct(p);}catch(e){setMsg(e.message||'Product not found.','error');}finally{addBtn.disabled=false;}}
function renderCustomer(payload,id,forcePopup=false){const stats=payload?.stats||{sales_count:0,total_spent:0,last_sale:null},sales=Array.isArray(payload?.recent_sales)?payload.recent_sales:[],favorites=Array.isArray(payload?.favorite_items)?payload.favorite_items:[],c=payload?.customer||null;
const customerNameValue=((c&&c.name)||stats?.last_sale?.customer_name||'').trim(),customerAddressValue=((c&&c.address)||stats?.last_sale?.customer_address||'').trim(),customerIdentifierValue=((c&&c.mobile)||(c&&c.identifier)||id||'').trim();
lastCustomerPayload=payload;lastCustomerIdentifier=id;
if(c&&customerId){customerId.value=String(c.id);}else if(customerId){customerId.value='';}
if(isPhoneOrWhatsappSource()){if(customerName)customerName.value=customerNameValue;}else if(customerName&&!customerName.value.trim()){customerName.value=customerNameValue;}
setAddressFields({address_line1:c?.address_line1||'',road:c?.road||'',sector:c?.sector||'',city:c?.city||'',pincode:c?.pincode||'',preference:c?.preference||''},isPhoneOrWhatsappSource());
const hasContext=Boolean(c||stats.last_sale||(stats.sales_count||0)>0);
if(!hasContext){setCustomerHtml('New Customer!!');if(repeatSummary)repeatSummary.innerHTML='';if(repeatOrders)repeatOrders.innerHTML='';if(repeatFavorites)repeatFavorites.innerHTML='';return;}
setCustomerHtml('Customer context loaded. Details available in popup.');
if(repeatTitle)repeatTitle.textContent=(stats.sales_count||0)>0?'Repeat Customer Found':'Customer Lookup';
if(repeatSummary){repeatSummary.innerHTML=`<div><strong>Identifier:</strong> ${esc(customerIdentifierValue||id||'-')}</div><div><strong>Name:</strong> ${esc(customerNameValue||'Not available')}</div><div><strong>Address:</strong> ${esc(customerAddressValue||'Not available')}</div><div><strong>Preference:</strong> ${esc(c?.preference||'Not available')}</div><div class="mt-1"><strong>Visits:</strong> ${stats.sales_count||0} | <strong>Lifetime Spend:</strong> Rs.${num(stats.total_spent).toFixed(2)}</div>${stats.last_sale?`<div><strong>Last Sale:</strong> ${esc(stats.last_sale.bill_number)} on ${esc(stats.last_sale.date||'')}</div>`:'<div><strong>Last Sale:</strong> Not available</div>'}`;}
if(repeatOrders){repeatOrders.innerHTML=sales.length?sales.map(s=>`<li>${esc(s.bill_number)} | ${esc((s.order_source||'').toUpperCase())} | Rs.${num(s.total_amount).toFixed(2)} | ${esc(s.date||'')}</li>`).join(''):'<li>No previous invoices found.</li>';}
if(repeatFavorites){repeatFavorites.innerHTML=favorites.length?favorites.map(f=>`<li>${esc(f.code)} - ${esc(f.name)} | Qty ${num(f.total_qty).toFixed(2)} | Orders ${f.order_count}</li>`).join(''):'<li>No favorite items captured yet.</li>';}
if(repeatModal){const popupKey=`${id}|${stats.sales_count||0}|${stats.last_sale?.bill_number||''}|${c?.id||''}`;if(forcePopup||lastRepeatPopupKey!==popupKey){lastRepeatPopupKey=popupKey;repeatModal.show();}}}
async function lookupCustomer(forcePopup=false){const id=normId(customerIdentifier.value);if(!id){setCustomerHtml('Enter mobile/identifier to check history.','error');if(customerId)customerId.value='';return;}
customerLookupBtn.disabled=true;setCustomerHtml('Checking purchase history...');try{const r=await fetch(`${customerLookupUrl}?identifier=${encodeURIComponent(id)}`,{headers:{'Accept':'application/json'}});const p=await r.json();if(!r.ok)throw new Error((p&&p.message)||'Unable to lookup customer.');renderCustomer(p,id,forcePopup);}catch(e){if(customerId)customerId.value='';setCustomerHtml(esc(e.message||'Unable to lookup customer.'),'error');}finally{customerLookupBtn.disabled=false;}}
function fillReview(){const s=summary(),items=activeItems();reviewItems.innerHTML='';items.forEach(it=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${esc(it.code)}</td><td>${esc(it.name)}</td><td class="text-end">${it.quantity}</td><td class="text-end">Rs.${it.price.toFixed(2)}</td><td class="text-end">Rs.${(it.quantity*it.price).toFixed(2)}</td>`;reviewItems.appendChild(tr);});
rSource.textContent=(orderSource.value||'outlet').toUpperCase();rCustomer.textContent=(customerName.value||'').trim()||(customerIdentifier.value||'').trim()||'Walk-in / Unknown';rSub.textContent=s.sub.toFixed(2);rDisc.textContent=s.d.toFixed(2);rTax.textContent=s.tx.toFixed(2);rRound.textContent=s.ro.toFixed(2);rTotal.textContent=s.total.toFixed(2);rPaid.textContent=s.pd.toFixed(2);rBalance.textContent=s.bal.toFixed(2);}
addBtn.addEventListener('click',()=>{if(highlight>=0&&suggestions[highlight]){addProduct(suggestions[highlight]);return;}addByCode();});
if(checkoutBtn){checkoutBtn.addEventListener('click',()=>{if(submitActionInput)submitActionInput.value='invoice';confirmed=false;form.requestSubmit();});}
customerLookupBtn.addEventListener('click',()=>lookupCustomer(true));
customerIdentifier.addEventListener('input',()=>{const digits=(customerIdentifier.value||'').replace(/\D/g,'').slice(0,10);if(digits!==''||/[\d\s+()-]/.test(customerIdentifier.value||'')){customerIdentifier.value=digits;}if(customerId)customerId.value='';lastRepeatPopupKey='';lastCustomerPayload=null;lastCustomerIdentifier='';setCustomerHtml('');});
customerIdentifier.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();lookupCustomer(true);}});
customerIdentifier.addEventListener('blur',()=>{if((customerIdentifier.value||'').trim()!==''){lookupCustomer(false);}});
orderSource.addEventListener('change',()=>{applyOrderSourceRules();if(lastCustomerPayload&&lastCustomerIdentifier){applyCustomerAutofill(lastCustomerPayload);}});
addressInputs.forEach((input)=>{if(input)input.addEventListener('input',syncHiddenAddress);});
codeInput.addEventListener('input',()=>{setMsg('');if(searchTimer)clearTimeout(searchTimer);searchTimer=setTimeout(()=>searchProducts(codeInput.value),150);});
codeInput.addEventListener('keydown',e=>{if((e.key==='ArrowDown'||e.key==='ArrowUp')&&suggestions.length){e.preventDefault();const d=e.key==='ArrowDown'?1:-1;highlight=(highlight+d+suggestions.length)%suggestions.length;renderSuggestions();return;}if(e.key==='Escape'){clearSuggestions();return;}if(e.key==='Enter'){e.preventDefault();if(highlight>=0&&suggestions[highlight]){addProduct(suggestions[highlight]);return;}if(suggestions.length===1){addProduct(suggestions[0]);return;}addByCode();}});
codeInput.addEventListener('blur',()=>setTimeout(clearSuggestions,120));
cartBody.addEventListener('input',e=>{const t=e.target;if(!t.classList.contains('cart-qty-input'))return;const id=String(t.dataset.productId||'');if(!cart.has(id))return;const it=cart.get(id);it.quantity=Math.max(0,Math.floor(num(t.value)));cart.set(id,it);const line=t.closest('tr')?.querySelector('.line-total');if(line)line.textContent=(it.quantity*it.price).toFixed(2);recalc();});
cartBody.addEventListener('click',e=>{const t=e.target;if(!t.classList.contains('remove-item-btn'))return;const id=String(t.dataset.productId||'');if(!id)return;cart.delete(id);renderCart();});
[discount,tax,roundOff,paid].forEach(i=>i&&i.addEventListener('input',recalc));
form.addEventListener('submit',e=>{rebuildHidden();if(!activeItems().length){e.preventDefault();setMsg('Add at least one item before checkout.','error');return;}if(customerIdentifier&&!customerIdentifier.checkValidity()){e.preventDefault();customerIdentifier.reportValidity();return;}if(customerPincode&&!customerPincode.checkValidity()){e.preventDefault();customerPincode.reportValidity();return;}if(paid&&!paid.checkValidity()){e.preventDefault();paid.reportValidity();return;}if(!confirmed&&modal){e.preventDefault();fillReview();modal.show();}});
if(confirmBtn){confirmBtn.addEventListener('click',()=>{if(submitActionInput)submitActionInput.value='invoice';confirmed=true;confirmBtn.disabled=true;form.submit();});}
if(modalEl){modalEl.addEventListener('hidden.bs.modal',()=>{confirmed=false;if(confirmBtn)confirmBtn.disabled=false;});}
document.querySelectorAll('.js-invoice-preview').forEach((btn)=>{btn.addEventListener('click',()=>{if(!invoicePreviewModal||!invoicePreviewFrame||!invoicePreviewPdf)return;const invoiceUrl=btn.getAttribute('data-invoice-url')||'about:blank',pdfUrl=btn.getAttribute('data-pdf-url')||'#';invoicePreviewFrame.src=invoiceUrl;invoicePreviewPdf.href=pdfUrl;invoicePreviewModal.show();});});
if(invoicePreviewModalEl&&invoicePreviewFrame){invoicePreviewModalEl.addEventListener('hidden.bs.modal',()=>{invoicePreviewFrame.src='about:blank';});}
if(Array.isArray(initialItems)){initialItems.forEach(it=>{const q=Math.max(0,Math.floor(num(it.quantity)));if(q>0)upsert(it,q);});}
applyGstRules();applyOrderSourceRules();syncHiddenAddress();renderCart();if(customerIdentifier&&customerIdentifier.value.trim()!==''){lookupCustomer();}
})();
</script>
@endsection

