<?php

namespace sicem\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use sicem\Nicho;
use sicem\User;
use sicem\Contrato;
use sicem\Difunto;
use sicem\Solicitante;
use sicem\Convenio;
use sicem\PlanPago;
use Carbon\Carbon;
use sicem\CSExtra;
use sicem\ServicioExtra;
use sicem\Traslado;
use sicem\Pabellon;


class NichoController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(){
       
    }

    public function getVerNicho(Request $request)
    {
        $this->validate($request, [
            'nicho_id' => 'required',
        ]);

        $now = Carbon::now();

        $nicho = Nicho::findOrFail($request->get('nicho_id'));

        $pabellon = Pabellon::findOrFail($nicho->pab_id);
        
        $usuarios = User::where('tipo','administrador')->get();
        if (!(session()->has('contrato')))
        {
            if ($nicho->nicho_est == "libre"){
                return view('gerencia.nicho.comprar',['nicho'=>$nicho, 'usuarios'=>$usuarios]);
            }
            if ($nicho->nicho_est == "tramite"){
                
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->where('cont_estado','tramite')->get()[0];

                $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
                return view('gerencia.nicho.tramite',['contrato'=>$contrato,'nicho'=>$nicho,'planpagos'=>$planpagos,'now'=>$now,'pabellon'=>$pabellon]);
            }
            if ($nicho->nicho_est == "ocupado") {

                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->where('cont_estado','<>','fenecido')->get()[0];
                $serviciosextra = \DB::select("SELECT * FROM t_servicioextra WHERE t_servicioextra.sextra_id NOT IN (SELECT cs.sextra_id FROM t_csextra cs WHERE cs.cont_id = '$contrato->cont_id')");
                
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->orderBy('cont_fecha','DESC')->get()[0];
                $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
                
                $tras_flag = $now->diffInYears(new Carbon($contrato->cont_fecha));
                return view('gerencia.nicho.mostrar',['nicho'=>$nicho,'contrato'=>$contrato,'now'=>$now,'planpagos'=>$planpagos,'serviciosextra'=>$serviciosextra,'pabellon'=>$pabellon,'tras_flag'=>$tras_flag]);
            }
            if ($nicho->nicho_est == "ttramite") {
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->get()[0];
                $traslado = Traslado::where(function($q) use($contrato){$q->where('cont_id_ant',$contrato->cont_id)->orwhere('cont_id_nue',$contrato->cont_id);})->where('tras_est','=','ttramite')->get()[0];
                $contrato_ant = Contrato::findOrFail($traslado->cont_id_ant);
                $contrato_nue = Contrato::findOrFail($traslado->cont_id_nue);

                $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
                return view('gerencia.nicho.ttramite',['traslado'=>$traslado,'contrato_ant'=>$contrato_ant,'contrato_nue'=>$contrato_nue,'nicho'=>$nicho,'planpagos'=>$planpagos,'now'=>$now,'pabellon'=>$pabellon]);
            }
            if ($nicho->nicho_est == "ltraslado") {
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->where('cont_estado','<>','fenecido')->get()[0];
                $serviciosextra = \DB::select("SELECT * FROM t_servicioextra WHERE t_servicioextra.sextra_id NOT IN (SELECT cs.sextra_id FROM t_csextra cs WHERE cs.cont_id = '$contrato->cont_id')");
                
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->orderBy('cont_fecha','DESC')->get()[0];
                $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
                
                $tras_flag = $now->diffInYears(new Carbon($contrato->cont_fecha));
                return view('gerencia.nicho.traslado',['nicho'=>$nicho,'contrato'=>$contrato,'now'=>$now,'planpagos'=>$planpagos,'serviciosextra'=>$serviciosextra,'pabellon'=>$pabellon,'tras_flag'=>$tras_flag]);
            }
            else {
                return "Contacte al administrador para ver este nicho";
            }
        }
        else
        {
            if ($nicho->nicho_est == "libre"){
                $contrato = Contrato::findOrFail(session('contrato'));
                session(['pasot' => 0]);
                return view('gerencia.nicho.trasladar', ['contrato'=>$contrato,'nicho'=>$nicho,'usuarios'=>$usuarios]);
            }
            else {

                $contrato = Contrato::findOrFail(session('contrato'));
                $serviciosextra = \DB::select("SELECT * FROM t_servicioextra WHERE t_servicioextra.sextra_id NOT IN (SELECT cs.sextra_id FROM t_csextra cs WHERE cs.cont_id = '$contrato->cont_id')");
                
                $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->orderBy('cont_fecha','DESC')->get()[0];
                $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
                session()->forget('contrato');
                return view('gerencia.nicho.mostrar',['nicho'=>$nicho,'contrato'=>$contrato,'now'=>$now,'planpagos'=>$planpagos,'serviciosextra'=>$serviciosextra,'pabellon'=>$pabellon])->with('error', "Fallo al realizar el traslado. El nicho escogido no se encuentra disponible. Inténtelo de nuevo");
            }
            
        }
    }

    public function getVerNichoAdmin(Request $request){
        $this->validate($request, [
            'nicho_id' => 'required',
        ]);

        $nicho = Nicho::findOrFail($request->get('nicho_id'));
        
        return view('nicho.mostrar',['nicho'=>$nicho]);
    }

    public function postEditarImagenNicho(Request $request){
        $nicho = Nicho::findOrFail($request->get('nicho_id'));
        $imagen = $request->file('nicho_pathimag');
      
        $nombre = $nicho->nicho_id.".".$imagen->guessExtension();
        $nicho->nicho_pathimag = $nicho->nicho_id.".".$imagen->guessExtension();
        $nicho->save();
        if ($imagen->guessExtension() == 'png') {
            $original = imagecreatefrompng($imagen);
        }
        if ($imagen->guessExtension() == 'jpeg') {
            $original = imagecreatefromjpeg($imagen);
        }
              
              
        $ancho_original = imagesx($original);
        $alto_original = imagesy($original);

        $ancho_nuevo = 240;
        $alto_nuevo = round($ancho_nuevo * $alto_original / $ancho_original);

        $copia =imagecreatetruecolor($ancho_nuevo , $alto_nuevo);

        imagecopyresampled($copia, $original, 0,0,0,0, $ancho_nuevo, $alto_nuevo, $ancho_original, $alto_original);

        imagejpeg($copia , public_path()."/assets/images/nicho/".$nombre, 100);

        $now = Carbon::now();

        $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->where('cont_estado','<>','fenecido')->get()[0];
        $serviciosextra = \DB::select("SELECT * FROM t_servicioextra WHERE t_servicioextra.sextra_id NOT IN (SELECT cs.sextra_id FROM t_csextra cs WHERE cs.cont_id = '$contrato->cont_id')");
        
        $contrato = Contrato::where('nicho_id',$nicho->nicho_id)->orderBy('cont_fecha','DESC')->get()[0];
        $planpagos = PlanPago::where('conv_id',$contrato->Convenio->conv_id)->orderBy('ppago_fechaven')->get();
        return view('gerencia.nicho.mostrar',['nicho'=>$nicho,'contrato'=>$contrato,'now'=>$now,'planpagos'=>$planpagos,'serviciosextra'=>$serviciosextra])->with('editado', 'Imagen editada exitósamente');
    }
}