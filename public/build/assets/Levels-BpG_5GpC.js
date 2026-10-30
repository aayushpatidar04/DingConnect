import{D as e,F as t,H as n,L as r,O as i,P as a,R as o,S as s,V as c,g as l,h as u,l as ee,m as d,p as f,r as p,s as m,t as te,u as h,v as g,x as _,y as v,z as y}from"./app-BTltGBhK.js";import{t as b}from"./AdminLayout-C-lAQUNF.js";import{t as ne}from"./FlashMessage-0YWKKvHu.js";import{t as x}from"./InputError-BsozNxWF.js";import{t as re}from"./SearchableSelect-DhRfGKqz.js";import{t as ie}from"./CommissionTabs-DrknI7yM.js";import{t as S}from"./CommissionModal-Dmb65FdF.js";import{a as C,i as w,r as T,s as E,t as D}from"./useCommission-COwuWZ8f.js";var ae={class:`space-y-6`},oe={class:`flex flex-wrap items-stretch gap-3`},se=[`onClick`],ce={class:`flex items-center gap-2`},le={class:`font-semibold`},ue={key:0,class:`text-[10px] uppercase px-1.5 py-0.5 rounded bg-surface-3 text-ink-500`},de={class:`text-xs text-ink-500 mt-0.5`},fe={class:`text-xs text-ink-500`},pe={key:0,class:`bg-surface-2 rounded-2xl border border-surface-3 p-8 text-center text-ink-500`},me={key:1,class:`bg-surface-2 rounded-2xl border border-surface-3 overflow-hidden`},he={class:`p-4 border-b border-surface-3 flex flex-wrap items-center justify-between gap-3`},ge={class:`text-xl font-semibold text-ink-900`},_e={class:`text-sm text-ink-500`},O={class:`flex flex-wrap gap-2`},k={class:`overflow-x-auto`},A={class:`min-w-full divide-y divide-surface-3`},j={class:`bg-surface-3`},M={class:`divide-y divide-surface-3`},N={class:`px-4 py-3 text-sm text-ink-900`},P={class:`px-4 py-3 text-sm text-ink-700 whitespace-nowrap`},F={class:`px-4 py-3 text-sm text-ink-700`},I={class:`px-4 py-3 text-sm text-ink-700`},ve={class:`px-4 py-3 text-sm font-medium text-ink-900`},ye={class:`px-4 py-3 text-sm text-ink-700`},be={class:`px-4 py-3 text-sm text-ink-700`},xe={key:1,class:`text-ink-500`},Se={class:`px-4 py-3 text-sm`},Ce={class:`px-4 py-3 text-right text-sm whitespace-nowrap`},we=[`onClick`],Te=[`onClick`],Ee={key:0,class:`p-8 text-center text-ink-500`},De={class:`flex items-center gap-2 text-sm text-ink-700`},Oe={class:`flex justify-end gap-2 pt-2`},ke=[`disabled`],Ae={class:`space-y-4`},je={class:`text-sm text-ink-500`},Me=[`value`],Ne={class:`flex justify-end gap-2`},Pe=[`disabled`],Fe={class:`grid grid-cols-2 gap-4`},Ie={class:`grid grid-cols-2 gap-4`},Le={class:`grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-end`},Re=[`onUpdate:modelValue`],ze=[`onUpdate:modelValue`],Be=[`onUpdate:modelValue`],Ve=[`onClick`],He={key:0,class:`text-xs text-ink-500`},Ue={class:`rounded-xl bg-surface-3 px-4 py-3 text-sm text-ink-700 flex flex-wrap gap-x-6 gap-y-1`},We={class:`text-ink-900`},Ge={class:`text-ink-900`},Ke={class:`flex items-center gap-2 text-sm text-ink-700`},qe={class:`flex justify-end gap-2 pt-2`},Je=[`disabled`],L=`w-full border border-surface-3 rounded-lg px-3 py-2 bg-surface-3 text-ink-900 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none`,R=`block text-xs font-medium text-ink-500 mb-1`,z=Object.assign({layout:b},{__name:`Levels`,props:{levels:{type:Array,default:()=>[]},selectedLevelId:{type:Number,default:null},rates:{type:Array,default:()=>[]},operators:{type:Array,default:()=>[]}},setup(b){let z=b,B=u(()=>z.levels.find(e=>e.id===z.selectedLevelId)??null);function V(e){let t=z.levels.filter(t=>t.min_connections>e.min_connections).sort((e,t)=>e.min_connections-t.min_connections)[0];return t?`${e.min_connections}–${t.min_connections-1} connections`:`${e.min_connections}+ connections`}function Ye(e){n.get(`/admin/commission-levels`,{level:e},{preserveState:!0,preserveScroll:!0})}let H=r(!1),U=p({id:null,name:``,min_connections:0,is_active:!0});function W(e=null){U.clearErrors(),U.id=e?.id??null,U.name=e?.name??``,U.min_connections=e?.min_connections??0,U.is_active=e?.is_active??!0,H.value=!0}function G(){let e={preserveScroll:!0,onSuccess:()=>H.value=!1};U.id?U.put(`/admin/commission-levels/${U.id}`,e):U.post(`/admin/commission-levels`,e)}function Xe(e){confirm(`Delete ${e.name} and all of its rates?`)&&n.delete(`/admin/commission-levels/${e.id}`)}let K=e=>String(e??``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#039;`})[e]);function Ze(){if(!B.value)return;new Date().toLocaleString(`en-GB`,{timeZone:`Europe/London`,day:`2-digit`,month:`short`,year:`numeric`,hour:`2-digit`,minute:`2-digit`});let e=z.rates.filter(e=>e.is_active).length;z.rates.length-e;let t=z.rates.map(e=>`
        <tr>
            <td class="operator">${K(e.operator?.name??`All operators`)}</td>
            <td class="range">${K(D(e))}</td>
            <td class="money">${K(C(e.first_topup_amount))}</td>
            <td class="money">${K(C(e.first_bonus_amount))}</td>
            <td class="money total">${K(C(T(e)))}</td>
            <td class="steps">${K(E(e))}</td>
        </tr>
    `).join(``)||`<tr><td colspan="8" class="empty">No rates on this level yet.</td></tr>`,n=`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commission Level – ${K(B.value.name)}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 12mm;
            @bottom-center {
                content: "MK All Network Communications · Commission Level Export · Page " counter(page) " of " counter(pages);
                font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
                font-size: 8pt;
                color: #6b7280;
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 9pt;
            color: #1f2937;
            line-height: 1.4;
            margin: 0;
            background: #fff;
        }
        .header {
            border-bottom: 3px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .brand { font-weight: 800; font-size: 11pt; letter-spacing: 0.5px; color: #111827; text-transform: uppercase; }
        .title { font-size: 17pt; font-weight: 700; color: #111827; margin: 0 0 2px 0; }
        .subtitle { font-size: 9pt; color: #6b7280; margin: 0; }
        .meta { font-size: 8pt; color: #6b7280; margin-top: 6px; }
        .meta b { color: #111827; }
        .meta-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 16px; margin: 14px 0 2px 0; font-size: 8pt; color: #6b7280; }
        .meta-item b { display: block; font-size: 10pt; color: #111827; font-weight: 700; margin-bottom: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th {
            background: #111827;
            color: #fff;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 700;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #111827;
        }
        td {
            padding: 5px 8px;
            border: 1px solid #d1d5db;
            font-size: 8pt;
            vertical-align: middle;
        }
        tr:nth-child(even) td { background: #f9fafb; }
        tr { page-break-inside: avoid; }
        .money { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
        .total { font-weight: 700; color: #111827; }
        .steps { white-space: pre-wrap; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-off { background: #e5e7eb; color: #6b7280; }
        .empty { text-align: center; color: #9ca3af; padding: 20px; }
        .footer { margin-top: 18px; font-size: 7pt; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">MK All Network Communications</div>
            <div class="title">Commission Level — ${K(B.value.name)}</div>
            <p class="subtitle">
                ${K(V(B.value))}
            </p>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Operator</th>
                <th>Top-up amount range</th>
                <th>First top-up (£)</th>
                <th>Bonus (£)</th>
                <th>First total (£)</th>
                <th>Ongoing top-ups</th>
            </tr>
        </thead>
        <tbody>
            ${t}
        </tbody>
    </table>
    <div class="footer">
        MK All Network Communications. All amounts in GBP.
    </div>
</body>
</html>`,r=document.createElement(`div`);r.className=`pdf-export-container`,r.innerHTML=n,document.body.appendChild(r),window.print(),setTimeout(()=>{document.body.contains(r)&&document.body.removeChild(r)},1e3)}let q=r(!1),J=r(``);function Qe(){J.value&&B.value&&n.post(`/admin/commission-levels/${B.value.id}/copy-rates`,{source_level_id:J.value},{preserveScroll:!0,onSuccess:()=>q.value=!1})}let Y=r(!1),X=(e=2)=>({from_sequence:e,to_sequence:``,amount:``}),Z=p({id:null,commission_level_id:null,operator_id:``,min_amount:0,max_amount:``,first_topup_amount:0,first_bonus_amount:0,is_active:!0,steps:[X()]});function Q(e=null){Z.clearErrors(),Z.id=e?.id??null,Z.commission_level_id=B.value?.id??null,Z.operator_id=e?.operator_id??``,Z.min_amount=e?.min_amount??0,Z.max_amount=e?.max_amount??``,Z.first_topup_amount=e?.first_topup_amount??0,Z.first_bonus_amount=e?.first_bonus_amount??0,Z.is_active=e?.is_active??!0,Z.steps=e?.steps?.length?e.steps.map(e=>({from_sequence:e.from_sequence,to_sequence:e.to_sequence??``,amount:e.amount})):[X()],Y.value=!0}function $e(){let e=Z.steps[Z.steps.length-1],t=e&&e.to_sequence?Number(e.to_sequence)+1:2;Z.steps.push(X(t))}function et(e){Z.steps.splice(e,1)}function tt(){Z.transform(e=>({...e,operator_id:e.operator_id||null,max_amount:e.max_amount===``?null:e.max_amount})).submit(Z.id?`put`:`post`,Z.id?`/admin/commission-rates/${Z.id}`:`/admin/commission-rates`,{preserveScroll:!0,onSuccess:()=>Y.value=!1})}function nt(e){confirm(`Delete this rate?`)&&n.delete(`/admin/commission-rates/${e.id}`,{preserveScroll:!0})}let $=u(()=>({first_topup_amount:Z.first_topup_amount,first_bonus_amount:Z.first_bonus_amount,steps:Z.steps.filter(e=>e.amount!==``)}));return(n,r)=>(e(),v(d,null,[s(o(te),{title:`Commission Levels - Admin`}),s(ne),l(`div`,ae,[r[21]||=l(`div`,null,[l(`h1`,{class:`text-3xl font-bold text-ink-900 mb-1`},`Commission`),l(`p`,{class:`text-ink-500`},` Cashback paid to retailers on every successful recharge, by level, operator and amount range. `)],-1),s(ie),l(`div`,oe,[(e(!0),v(d,null,i(b.levels,t=>(e(),v(`button`,{key:t.id,type:`button`,onClick:e=>Ye(t.id),class:y([`text-left px-4 py-3 rounded-xl border transition min-w-[170px]`,t.id===b.selectedLevelId?`bg-primary/10 border-primary text-ink-900`:`bg-surface-2 border-surface-3 text-ink-700 hover:border-primary/50`])},[l(`div`,ce,[l(`span`,le,c(t.name),1),t.is_active?g(``,!0):(e(),v(`span`,ue,`Inactive`))]),l(`div`,de,c(V(t)),1),l(`div`,fe,c(t.retailers_count)+` retailer`+c(t.retailers_count===1?``:`s`)+` · `+c(t.rates_count)+` rate`+c(t.rates_count===1?``:`s`),1)],10,se))),128)),l(`button`,{type:`button`,onClick:r[0]||=e=>W(),class:`px-4 py-3 rounded-xl border border-dashed border-surface-3 text-ink-500 hover:text-ink-900 hover:border-primary transition text-sm font-medium`},` + New level `)]),b.levels.length?g(``,!0):(e(),v(`div`,pe,` No levels yet. Create one, or run the commission seeder to load the starting levels and rates. `)),B.value?(e(),v(`div`,me,[l(`div`,he,[l(`div`,null,[l(`h2`,ge,c(B.value.name),1),l(`p`,_e,c(V(B.value)),1)]),l(`div`,O,[l(`button`,{type:`button`,onClick:r[1]||=e=>W(B.value),class:`px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3 transition`},` Edit level `),!b.rates.length&&b.levels.length>1?(e(),v(`button`,{key:0,type:`button`,onClick:r[2]||=e=>q.value=!0,class:`px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3 transition`},` Copy rates from… `)):g(``,!0),l(`button`,{type:`button`,onClick:Ze,class:`px-3 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3 transition`},` Export PDF `),l(`button`,{type:`button`,onClick:r[3]||=e=>Xe(B.value),class:`px-3 py-2 border border-red-500/40 rounded-lg text-sm text-red-600 hover:bg-red-500/10 transition`},` Delete `),l(`button`,{type:`button`,onClick:r[4]||=e=>Q(),class:`px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark transition`},` + Add rate `)])]),l(`div`,k,[l(`table`,A,[l(`thead`,j,[l(`tr`,null,[(e(),v(d,null,i([`Operator`,`Top-up amount`,`Top up`,`Bonus`,`First total`,`Ongoing top-ups`,`Lifetime total`,`Status`,``],e=>l(`th`,{key:e,class:`px-4 py-3 text-left text-xs font-medium text-ink-700 uppercase tracking-wider whitespace-nowrap`},c(e),1)),64))])]),l(`tbody`,M,[(e(!0),v(d,null,i(b.rates,t=>(e(),v(`tr`,{key:t.id},[l(`td`,N,c(t.operator?.name??`All operators`),1),l(`td`,P,c(o(D)(t)),1),l(`td`,F,c(o(C)(t.first_topup_amount)),1),l(`td`,I,c(o(C)(t.first_bonus_amount)),1),l(`td`,ve,c(o(C)(o(T)(t))),1),l(`td`,ye,c(o(E)(t)),1),l(`td`,be,[o(w)(t)===null?(e(),v(`span`,xe,`No limit`)):(e(),v(d,{key:0},[_(c(o(C)(o(w)(t))),1)],64))]),l(`td`,Se,[l(`span`,{class:y([`px-2 py-0.5 rounded-full text-xs`,t.is_active?`bg-green-500/20 text-green-600`:`bg-surface-3 text-ink-500`])},c(t.is_active?`Active`:`Off`),3)]),l(`td`,Ce,[l(`button`,{type:`button`,onClick:e=>Q(t),class:`text-primary-light hover:text-primary mr-3`},` Edit `,8,we),l(`button`,{type:`button`,onClick:e=>nt(t),class:`text-red-600 hover:text-red-500`},` Delete `,8,Te)])]))),128))])])]),b.rates.length?g(``,!0):(e(),v(`div`,Ee,` No rates on this level yet. Retailers on it earn nothing until you add some. `))])):g(``,!0)]),s(S,{show:H.value,title:o(U).id?`Edit level`:`New level`,onClose:r[9]||=e=>H.value=!1},{default:a(()=>[l(`form`,{onSubmit:f(G,[`prevent`]),class:`space-y-4`},[l(`div`,null,[l(`label`,{class:y(R)},`Name`),t(l(`input`,{"onUpdate:modelValue":r[5]||=e=>o(U).name=e,type:`text`,class:y(L)},null,512),[[h,o(U).name]]),s(x,{message:o(U).errors.name},null,8,[`message`])]),l(`div`,null,[l(`label`,{class:y(R)},`Starts at (connections)`),t(l(`input`,{"onUpdate:modelValue":r[6]||=e=>o(U).min_connections=e,type:`number`,min:`0`,class:y(L)},null,512),[[h,o(U).min_connections]]),r[22]||=l(`p`,{class:`text-xs text-ink-500 mt-1`},` A retailer reaches this level when their level connections pass this number. The level ends where the next level begins. `,-1),s(x,{message:o(U).errors.min_connections},null,8,[`message`])]),l(`label`,De,[t(l(`input`,{"onUpdate:modelValue":r[7]||=e=>o(U).is_active=e,type:`checkbox`,class:`rounded border-surface-3`},null,512),[[m,o(U).is_active]]),r[23]||=_(` Active (inactive levels pay nothing and are skipped by auto upgrade) `,-1)]),l(`div`,Oe,[l(`button`,{type:`button`,onClick:r[8]||=e=>H.value=!1,class:`px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3`},` Cancel `),l(`button`,{type:`submit`,disabled:o(U).processing,class:`px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60`},` Save `,8,ke)])],32)]),_:1},8,[`show`,`title`]),s(S,{show:q.value,title:`Copy rates`,onClose:r[12]||=e=>q.value=!1},{default:a(()=>[l(`div`,Ae,[l(`p`,je,` Copy every rate and ongoing band from another level into `+c(B.value?.name)+`. You can edit them afterwards. `,1),t(l(`select`,{"onUpdate:modelValue":r[10]||=e=>J.value=e,class:y(L)},[r[24]||=l(`option`,{value:``},`Choose a level…`,-1),(e(!0),v(d,null,i(b.levels.filter(e=>e.id!==B.value?.id),t=>(e(),v(`option`,{key:t.id,value:t.id},c(t.name)+` (`+c(t.rates_count)+` rates) `,9,Me))),128))],512),[[ee,J.value]]),l(`div`,Ne,[l(`button`,{type:`button`,onClick:r[11]||=e=>q.value=!1,class:`px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3`},` Cancel `),l(`button`,{type:`button`,onClick:Qe,disabled:!J.value,class:`px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60`},` Copy `,8,Pe)])])]),_:1},8,[`show`]),s(S,{show:Y.value,title:(o(Z).id?`Edit rate`:`Add rate`)+(B.value?` – ${B.value.name}`:``),"max-width":`max-w-2xl`,onClose:r[20]||=e=>Y.value=!1},{default:a(()=>[l(`form`,{onSubmit:f(tt,[`prevent`]),class:`space-y-5`},[l(`div`,null,[l(`label`,{class:y(R)},`Operator`),s(re,{modelValue:o(Z).operator_id,"onUpdate:modelValue":r[13]||=e=>o(Z).operator_id=e,options:b.operators,"all-label":`All operators`,"search-placeholder":`Search operators...`},null,8,[`modelValue`,`options`]),r[25]||=l(`p`,{class:`text-xs text-ink-500 mt-1`},` An operator-specific rate wins over an "All operators" rate for the same amount. `,-1),s(x,{message:o(Z).errors.operator_id},null,8,[`message`])]),l(`div`,Fe,[l(`div`,null,[l(`label`,{class:y(R)},`Top-up amount from (£)`),t(l(`input`,{"onUpdate:modelValue":r[14]||=e=>o(Z).min_amount=e,type:`number`,step:`0.01`,min:`0`,class:y(L)},null,512),[[h,o(Z).min_amount]]),s(x,{message:o(Z).errors.min_amount},null,8,[`message`])]),l(`div`,null,[l(`label`,{class:y(R)},`Up to (£) – leave empty for no limit`),t(l(`input`,{"onUpdate:modelValue":r[15]||=e=>o(Z).max_amount=e,type:`number`,step:`0.01`,min:`0`,class:y(L)},null,512),[[h,o(Z).max_amount]]),s(x,{message:o(Z).errors.max_amount},null,8,[`message`])])]),l(`div`,Ie,[l(`div`,null,[l(`label`,{class:y(R)},`First top-up: Top up (£)`),t(l(`input`,{"onUpdate:modelValue":r[16]||=e=>o(Z).first_topup_amount=e,type:`number`,step:`0.01`,min:`0`,class:y(L)},null,512),[[h,o(Z).first_topup_amount]]),s(x,{message:o(Z).errors.first_topup_amount},null,8,[`message`])]),l(`div`,null,[l(`label`,{class:y(R)},`First top-up: Bonus (£)`),t(l(`input`,{"onUpdate:modelValue":r[17]||=e=>o(Z).first_bonus_amount=e,type:`number`,step:`0.01`,min:`0`,class:y(L)},null,512),[[h,o(Z).first_bonus_amount]]),s(x,{message:o(Z).errors.first_bonus_amount},null,8,[`message`])])]),l(`div`,null,[l(`div`,{class:`flex items-center justify-between mb-2`},[r[26]||=l(`label`,{class:`text-xs font-medium text-ink-500`},`Ongoing top-ups (top-up number 2 onwards)`,-1),l(`button`,{type:`button`,onClick:$e,class:`text-sm text-primary-light hover:text-primary`},` + Add band `)]),(e(!0),v(d,null,i(o(Z).steps,(n,i)=>(e(),v(`div`,{key:i,class:`mb-2`},[l(`div`,Le,[l(`div`,null,[r[27]||=l(`span`,{class:`text-[11px] text-ink-500`},`From #`,-1),t(l(`input`,{"onUpdate:modelValue":e=>n.from_sequence=e,type:`number`,min:`2`,class:y(L)},null,8,Re),[[h,n.from_sequence]])]),l(`div`,null,[r[28]||=l(`span`,{class:`text-[11px] text-ink-500`},`To # (empty = no end)`,-1),t(l(`input`,{"onUpdate:modelValue":e=>n.to_sequence=e,type:`number`,min:`2`,class:y(L)},null,8,ze),[[h,n.to_sequence]])]),l(`div`,null,[r[29]||=l(`span`,{class:`text-[11px] text-ink-500`},`Pays each (£)`,-1),t(l(`input`,{"onUpdate:modelValue":e=>n.amount=e,type:`number`,step:`0.01`,min:`0`,class:y(L)},null,8,Be),[[h,n.amount]])]),l(`button`,{type:`button`,onClick:e=>et(i),class:`px-3 py-2 text-red-600 hover:text-red-500 text-sm`,"aria-label":`Remove band`},` ✕ `,8,Ve)]),s(x,{message:o(Z).errors[`steps.${i}.from_sequence`]||o(Z).errors[`steps.${i}.to_sequence`]||o(Z).errors[`steps.${i}.amount`]},null,8,[`message`])]))),128)),o(Z).steps.length?g(``,!0):(e(),v(`p`,He,` No ongoing bands: only the first top-up of a connection pays. `))]),l(`div`,Ue,[l(`span`,null,[r[30]||=_(`First top-up pays `,-1),l(`strong`,We,c(o(C)(o(T)($.value))),1)]),l(`span`,null,[r[31]||=_(` Lifetime per connection `,-1),l(`strong`,Ge,c(o(w)($.value)===null?`no limit`:o(C)(o(w)($.value))),1)])]),l(`label`,Ke,[t(l(`input`,{"onUpdate:modelValue":r[18]||=e=>o(Z).is_active=e,type:`checkbox`,class:`rounded border-surface-3`},null,512),[[m,o(Z).is_active]]),r[32]||=_(` Active `,-1)]),l(`div`,qe,[l(`button`,{type:`button`,onClick:r[19]||=e=>Y.value=!1,class:`px-4 py-2 border border-surface-3 rounded-lg text-sm text-ink-700 hover:bg-surface-3`},` Cancel `),l(`button`,{type:`submit`,disabled:o(Z).processing,class:`px-4 py-2 bg-primary text-ink-100 rounded-lg text-sm font-medium hover:bg-primary-dark disabled:opacity-60`},` Save rate `,8,Je)])],32)]),_:1},8,[`show`,`title`])],64))}});export{z as default};