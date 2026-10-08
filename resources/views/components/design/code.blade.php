{{--
    Code sample on the /design page. Wrap the sample in @verbatim.
    The slot is an HtmlString (rendered raw by {{ }}), so it is converted to
    a plain string first: the sample is displayed, never interpreted.
--}}
<pre {{ $attributes->class('overflow-x-auto rounded-xl bg-slate-900 px-4 py-3 text-sm leading-relaxed text-slate-100') }}><code>{{ $slot->toHtml() }}</code></pre>
