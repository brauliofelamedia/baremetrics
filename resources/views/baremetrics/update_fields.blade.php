@extends('layouts.admin')

@section('title', 'Actualizar campos desde GHL')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Actualizar campos de usuarios desde GoHighLevel</h4>
                    <p>Esta utilidad recorrerá los clientes en Baremetrics y actualizará campos con información proveniente de GoHighLevel.</p>

                    <div class="mb-3">
                        <button id="startBtn" class="btn btn-primary">Iniciar actualización</button>
                        <button id="stopBtn" class="btn btn-secondary" disabled>Detener (no implementado)</button>
                    </div>

                    <div class="mb-3">
                        <div class="progress">
                            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <p>Actualizados: <span id="updated">0</span> / <span id="total">0</span></p>
                        <p>Procesando: <strong id="currentEmail">-</strong></p>
                    </div>

                    <div>
                        <pre id="log" class="bg-dark text-light p-2" style="height:200px; overflow:auto;"></pre>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    console.log('baremetrics.update_fields script loaded');
    const startBtn = document.getElementById('startBtn');
    const progressBar = document.getElementById('progressBar');
    const updatedEl = document.getElementById('updated');
    const totalEl = document.getElementById('total');
    const currentEmailEl = document.getElementById('currentEmail');
    const logEl = document.getElementById('log');

    let polling = null;

    function appendLog(text){
        const now = new Date().toLocaleTimeString();
        logEl.textContent = `[${now}] ${text}\n` + logEl.textContent;
    }

    async function getStatus(){
        try{
            const res = await fetch("{{ route('admin.baremetrics.update-fields.status') }}", {credentials: 'same-origin'});
            const json = await res.json();
            if(json.success){
                const d = json.data;
                updatedEl.textContent = d.updated || 0;
                totalEl.textContent = d.total || 0;
                currentEmailEl.textContent = d.current_email || '-';
                const percent = d.total ? Math.round(((d.updated||0)/d.total)*100) : 0;
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                if(d.status === 'running'){
                    appendLog('En proceso: ' + (d.current_email || '-'));
                } else if(d.status === 'finished'){
                    appendLog('Proceso finalizado. Actualizados: ' + (d.updated || 0));
                    stopPolling();
                }
            }
        }catch(e){
            appendLog('Error al obtener estado: ' + e.message);
        }
    }

    function startPolling(){
        if(polling) return;
        polling = setInterval(getStatus, 3000);
    }

    function stopPolling(){
        if(polling){
            clearInterval(polling);
            polling = null;
        }
    }

    if(!startBtn){
        console.error('startBtn not found in DOM');
        return;
    }

    startBtn.addEventListener('click', async function(){
        startBtn.disabled = true;
        appendLog('Iniciando proceso...');
        try{
            const res = await fetch("{{ route('admin.baremetrics.update-fields.start') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            const json = await res.json();
            appendLog('Inicio solicitado. Respuesta: ' + JSON.stringify(json));
            startPolling();
            getStatus();
        }catch(e){
            appendLog('Error al iniciar: ' + e.message);
            startBtn.disabled = false;
        }
    });

})();
</script>
@endpush

@endsection
