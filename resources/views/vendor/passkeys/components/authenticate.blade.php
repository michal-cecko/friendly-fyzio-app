<div>
    @include('passkeys::components.partials.authenticateScript')

    <form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}">
        @csrf
    </form>

    @if($message = session()->get('authenticatePasskey::message'))
        <div class="bg-red-100 text-red-700 p-4 border border-red-400 rounded">
            {{ $message }}
        </div>
    @endif

    {{-- Passkeys (WebAuthn) need a secure context: HTTPS or localhost. On plain HTTP
         the browser exposes no PublicKeyCredential API, so hide the trigger rather than
         leave a button that silently fails on click. It reveals itself automatically
         once the page is served over HTTPS. --}}
    <div onclick="authenticateWithPasskey()" data-passkey-trigger style="display: none;">
        @if ($slot->isEmpty())
            <div class="underline cursor-pointer">
                {{ __('passkeys::passkeys.authenticate_using_passkey') }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>

    <script>
        if (window.PublicKeyCredential && window.isSecureContext) {
            document.querySelectorAll('[data-passkey-trigger]').forEach((el) => {
                el.style.display = '';
            });
        }
    </script>
</div>
