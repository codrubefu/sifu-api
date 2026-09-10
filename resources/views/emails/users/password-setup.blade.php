<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <p>Bună, {{ $user->first_name }},</p>

    @if($isNewAccount)
        <p>Contul tău a fost creat. Pentru a-l putea folosi, trebuie să îți setezi o parolă accesând linkul de mai jos:</p>
    @else
        <p>Am primit o cerere de resetare a parolei pentru contul tău. Poți seta o parolă nouă accesând linkul de mai jos:</p>
    @endif

    <p>
        <a href="{{ $link }}" style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:4px;">
            {{ $isNewAccount ? 'Setează-ți parola' : 'Resetează parola' }}
        </a>
    </p>

    <p>Dacă butonul de mai sus nu funcționează, copiază acest link în browser:</p>
    <p><a href="{{ $link }}">{{ $link }}</a></p>

    @unless($isNewAccount)
        <p>Dacă nu ai solicitat această resetare, poți ignora acest email.</p>
    @endunless

    <p>Acest link este valabil o perioadă limitată de timp.</p>
</body>
</html>
