{{-- The link spelled out, for when the button does not open. The panel says "tú"; an employee's email says "usted". --}}
<p class="fine">{{ $formal ? 'Si el botón no funciona, copie este enlace en su navegador:' : 'Si el botón no funciona, copia este enlace en tu navegador:' }}<br><a href="{{ $url }}" class="break-all">{{ $url }}</a></p>
