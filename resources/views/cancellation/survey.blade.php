@extends('layouts.auth')

@section('title', 'Cancelación de suscripción')

@push('styles')
    <style>
        .auth-container {
            background-image: none;
            background-color: #33334F;
        }
        .auth-card {
            padding:0;
            max-width: 500px;
        }

        .text {
            padding: 30px 30px 0 30px;
        }

        h3 {
            font-size: 22px;
            font-weight: 600;
        }

        p {
            font-size: 16px;
            line-height: 1.4em;
        }

        .blue-line {
            padding: 15px;
            background-color: #6078FF;
            color: white;
            font-size: 17px;
        }

        form {
            padding: 30px 30px 15px 30px;
        }

        .btn-cancel {
            background-color:#E84C85;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding:10px 20px;
            font-weight: 600;
            font-size: 20px;
        }

        .btn-cancel:hover {
            background-color:#db165e;
            color: white;
        }

        label {
            font-size: 16px;
            line-height: 1.3em;
            margin-bottom: 0;
        }

        .radio-option {
            position: relative;
            display: block;
            padding-left: 30px;
            cursor: pointer;
            margin-bottom:10px;
        }

        .radio-option input[type="radio"] {
            position: absolute;
            left: 0;
            top: 0;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 50%;
            outline: none;
            background: white;
        }

        .radio-option input[type="radio"]:checked {
            background: #007bff;
            border-color: #007bff;
        }

        .radio-option input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            left: 4px;
            top: 4px;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .link-back {
            margin-bottom: 20px;
            display: block;
        }

        #additional_comments {
            border-radius: 6px;
        }
    </style>
@endpush

@section('content')
<div class="auth-card">
    <!-- Mensajes de estado -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="text">
        <img src="{{asset('assets/img/sad.png')}}" alt="Sad">
        <h3>Te guardaremos tu lugar</h3>
        <p>Antes de partir, ayúdanos un segundo con la razón de tu decisión. Cada detallito que nos compartas vale oro para nosotros</p>
    </div>

    <div class="blue-line">
        Ayudanos porfis. Queremos mejorar 🙏🏼
    </div>

    <form action="{{route('cancellation.survey.save')}}" method="POST">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer_id }}">
        @if($customer && isset($customer['email']))
            <input type="hidden" name="email" value="{{ $customer['email'] }}">
        @endif
        <div class="form-group">
            @php
                $reasons = [
                    'No conecté con el estilo, enfoque o dinámica de la comunidad',
                    'No logré dedicarle el tiempo que consideraba apropiado para aprovecharla',
                    'Cambiaron mis prioridades. No era lo que necesitaba en esta etapa de mi negocio o vida',
                    'No le encontré el valor que esperaba por el dinero invertido',
                    'Dificultades económicas o imprevistos financieros',
                ];
                shuffle($reasons);
            @endphp
            @foreach($reasons as $reason)
                <label class="radio-option">
                    <input type="radio" name="reason" value="{{ $reason }}" required> {{ $reason }}
                </label>
            @endforeach
            <label class="radio-option">
                <input type="radio" name="reason" value="Otros" required> Otros
            </label>
        </div>
        <div class="form-group">
            <label for="additional_comments" style="font-weight: 600;">¿Algo que quieras compartir? (Opcional)</label>
            <textarea name="additional_comments" id="additional_comments" class="form-control" rows="2" placeholder="Comentarios..."></textarea>
        </div>
        <button type="submit" class="btn btn-cancel">Cancelar Créetelo®</button>
    </form>

    <a href="#" class="link-back">Uy no! que estoy haciendo?, mejor me quedo!</a>

</div>
@endsection