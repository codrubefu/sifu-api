# Raportul ciclului de viață al membrilor

Raportul folosește întregul istoric din `service_user`, limitat la serviciile organizației cerute. Perioada selectată limitează **evenimentele raportate**, nu istoricul consultat pentru a decide dacă o alocare este prima sau dacă există o alocare următoare.

## Data evenimentului

Începutul unei alocări este, în ordine, `start_date`, `activated_at`, apoi `created_at`. Alocările fără niciuna dintre aceste date sunt ignorate. Limitele perioadei sunt inclusive. Expirarea folosește `expires_at`, iar termenul-limită este `expires_at + services.grace_period_days`.

## Reguli de clasificare

Istoricul este separat după `(user_id, services.type)` și ordonat după data de început. „Următoarea” alocare înseamnă alocarea imediat următoare din același istoric.

* **Membru nou (`new_members`)** — prima alocare a utilizatorului pentru acel tip de serviciu. Evenimentul este înregistrat la începutul alocării.
* **Eligibil pentru reînnoire (`eligible_for_renewal`)** — orice alocare care are `expires_at`. Evenimentul este înregistrat exact la expirare.
* **Reînnoit (`renewed`)** — există o alocare următoare pentru același utilizator și același tip de serviciu, iar începutul ei este mai mic sau egal cu termenul-limită al alocării precedente. Egalitatea cu termenul-limită este deci o reînnoire. Evenimentul este înregistrat la începutul noii alocări.
* **Nereînnoit (`not_renewed`)** — nu există o alocare următoare de același tip sau aceasta începe strict după termenul-limită. Evenimentul este înregistrat la termenul-limită, nu la expirare. Astfel, raportul nu declară prematur o nereînnoire cât timp perioada de grație este deschisă.
* **Reactivat (`reactivated`)** — alocarea următoare de același tip începe strict după termenul-limită al celei precedente. Evenimentul este înregistrat la începutul noii alocări. O revenire poate coexista istoric cu un eveniment anterior `not_renewed`.

O alocare fără `expires_at` nu poate fi eligibilă, reînnoită, nereînnoită sau baza unei reactivări. Indicatorii sunt evenimente de ciclu de viață, nu categorii mutual exclusive de persoane; de exemplu, aceeași alocare poate produce atât `new_members`, cât și `eligible_for_renewal` în perioade diferite.

## Grupare

`group_by` acceptă un tablou sau un șir separat prin virgulă cu valorile `month`, `location` și `service_type`. Luna este luna datei evenimentului. Tipul este valoarea `services.type`. Locațiile provin din asocierea curentă `location_user`: un membru cu mai multe locații contribuie în fiecare locație, iar unul fără locație apare cu `location_id` și `location_name` nule. Din acest motiv, suma grupelor pe locație poate depăși totalul unic de evenimente; `totals` nu multiplică evenimentele după locație.
