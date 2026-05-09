<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement {{ $statut ? 'Réussi' : 'Échoué' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f3f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-card {
            background: linear-gradient(135deg, #e0e7ff 0%, #fffbe6 100%);
            border-radius: 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 400px;
            width: 100%;
            margin: 1rem;
            padding: 2rem 1.5rem 1.5rem 1.5rem;
        }
        .success-icon {
            background: #fff;
            border-radius: 50%;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 2px 8px rgba(40,167,69,0.08);
        }
        .success-icon i {
            font-size: 2.5rem;
        }
        .main-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #2d2d2d;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #4b5563;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .divider {
            border-top: 1px solid #e5e7eb;
            margin: 1.5rem 0;
        }
        .label {
            color: #6b7280;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.2rem;
        }
        .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #22223b;
            margin-bottom: 1rem;
        }
        .payment-method {
            background: #eef2ff;
            border-radius: 14px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .payment-method img, .payment-method i {
            width: 38px;
            height: 38px;
        }
        .download-btn {
            width: 100%;
            background: #22223b;
            color: #fff;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.8rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            margin-bottom: 1rem;
            transition: background 0.2s;
            text-decoration: none;
        }
        .download-btn:hover {
            background: #4a90e2;
            color: #fff;
        }
        .mobile-notice {
            text-align: center;
            color: #4b5563;
            font-size: 1rem;
            margin-top: 1.2rem;
        }
        @media (max-width: 500px) {
            .payment-card {
                padding: 1.2rem 0.5rem 1rem 0.5rem;
            }
            .main-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <div class="success-icon">
            <i class="fas {{ $statut ? 'fa-check text-success' : 'fa-times text-danger' }}"></i>
        </div>

        <div class="main-title">
            @if(isset($message) && $message === 'Paiement non trouvé ou déjà traité')
                Paiement introuvable
            @else
                {{ $statut ? 'Paiement réussi !' : 'Paiement échoué' }}
            @endif
        </div>

        <div class="subtitle">
            @if(isset($message) && $message === 'Paiement non trouvé ou déjà traité')
                <strong class="text-danger">
                    Le paiement est introuvable ou a déjà été traité. Veuillez vérifier vos informations ou réessayer plus tard.
                </strong>
            @else
                {{ $message ?? ($statut ? 'Nous avons bien reçu votre paiement.' : 'Le paiement n’a pas pu être traité.') }}
            @endif
        </div>

        <div class="divider"></div>

        <div class="label">Statut</div>
        <div class="value {{ $statut ? 'text-success' : 'text-danger' }}">
            <i class="fas {{ $statut ? 'fa-check-circle' : 'fa-times-circle' }} me-1"></i>
            {{ $statut ? 'Succès' : 'Échec' }}
        </div>

        <div class="label">Date</div>
        <div class="value">
            {{ \Carbon\Carbon::parse($data['date'] ?? now())->format('d M Y \à H\hi') }}
        </div>

        @if($statut && isset($data['channel']))
            <div class="label">Moyen de paiement</div>
            <div class="payment-method">
                @php
                    $channels = [
                        'WAVECI' => ['img' => 'https://upload.wikimedia.org/wikipedia/commons/6/6b/Wave_logo.png', 'name' => 'Wave'],
                        'MASTERCARD' => ['img' => 'https://upload.wikimedia.org/wikipedia/commons/0/04/Mastercard-logo.png', 'name' => 'Mastercard'],
                        'VISA' => ['img' => 'https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png', 'name' => 'Visa'],
                    ];
                    $channel = $data['channel'] ?? 'AUTRE';
                    $channelInfo = $channels[$channel] ?? ['img' => 'https://cdn-icons-png.flaticon.com/512/565/565547.png', 'name' => $channel];
                @endphp
                <img src="{{ $channelInfo['img'] }}" alt="{{ $channelInfo['name'] }}" style="background:#fff; border-radius:8px;">
                <div>
                    <div style="font-weight:600;">{{ $channelInfo['name'] }}</div>
                    @if(isset($data['card_last4']))
                        <div style="font-size:0.95rem;">Se terminant par {{ $data['card_last4'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if(!$statut)
            <a href="{{ route('paiement.retry', ['reference' => $reference]) }}" class="download-btn">
                <i class="fas fa-redo-alt"></i> Réessayer
            </a>
        @endif

        <div class="mobile-notice">
            <i class="fas fa-mobile-alt"></i><br>
            Pour continuer, retournez dans l’application mobile.
        </div>
    </div>
</body>
</html>
