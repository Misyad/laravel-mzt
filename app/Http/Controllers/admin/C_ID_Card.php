<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Berita;
use App\Models\ComponentTemplateIdCard;
use App\Models\TemplateIdCard;
use App\Models\User;
use App\Models\DataUser;
use App\Models\Transaksi_event;
use Illuminate\Support\Facades\File;
use DataPicker;

class C_ID_Card extends Controller
{
    function index()
    {
        \DataPicker::activitas_log('membuka tabel berita');
        $data = TemplateIdCard::all();
        return view('admin.IdCard.index', ['data' => $data]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,png,jpeg,gif,svg', 'max:1048'],
        ]);

        $imagePath = $request->file('foto')->store('image/id-card', 'public');

        $templateIdCards = TemplateIdCard::all();
        foreach ($templateIdCards as $templateIdCardAll) {
            $templateIdCardAll->status = 'NON-ACTIVE';
            $templateIdCardAll->save();
        }   

        $templateIdCard = TemplateIdCard::create([
            'path' => $imagePath,
            'status' => 'active'
        ]);

        \DataPicker::activitas_log('menambahkan id card');

        return redirect()->route('id-card.set-component', $templateIdCard->id);
    }

    public function printCard($idEvent, $idTransaction) {

        $templateIdCard = TemplateIdCard::where('status', 'ACTIVE')->first();
        $transactionAnggota = Transaksi_event::where(['id' => $idEvent])->first();
        $user = User::where('id_anggota', $transactionAnggota->id_anggota)->first();
        $dataUsers = DataUser::where('id_users', $user->id)->first();

        $dataUser = [
            'name' => $user->name,
            'niqobah' => $dataUsers->niqobah,
            'photo' => $dataUsers->foto,
            'idTransaction' => $transactionAnggota->id,
        ];
        
        $componentTemplateIdCard = ComponentTemplateIdCard::where('id_template', $templateIdCard->id)->get();

        return view('admin.IdCard.print_id_card', [
            'templateIdCard' => $templateIdCard,
            'componentTemplateIdCard' => $componentTemplateIdCard,
            'dataUser' => $dataUser
        ]);
        
    }

    function setComponent($id)
    {
        \DataPicker::activitas_log('setting id card');
     
        $data = TemplateIdCard::find($id);
        $component = ComponentTemplateIdCard::where('id_template', $id)->get();

        $photo = [];
        $name = [];
        $niqobah = [];

        foreach($component as $component) {
            if($component->title === 'Photo') $photo = $component;
            if($component->title === 'Name') $name = $component;
            if($component->title === 'Niqobah') $niqobah = $component;
        }

        return view('admin.IdCard.component_id_card', 
            [
                'data' => $data, 
                'id' => $id, 
                'photo' => $photo,
                'name' => $name,
                'niqobah' => $niqobah
            ]
        );
    }

    function deleteData(Request $request)
    {
        $request->validate([
            'id' => ['required'],
        ]);
        Berita::where(['id' => $request->id])->update([
            'is_active' => '0',
            'slug' => '',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'berhasil update data',
            'data'    => 'succes' 
        ],200);  
    }

    public function setLayoutComponent(Request $request)
    {
        $idTemplate = $request->post('id');
        $photoX = $request->post('photoX');
        $photoY = $request->post('photoY');
        $niqobahX = $request->post('niqobahX');
        $niqobahY = $request->post('niqobahY');
        $nameX = $request->post('nameX');
        $nameY = $request->post('nameY');

        $templateIdCardData = [
            'id_template' => $idTemplate,
        ];

        $photoData = $templateIdCardData + [
            'title' => 'Photo',
            'position_x' => $photoX,
            'position_y' => $photoY,
        ];

        $niqobahData = $templateIdCardData + [
            'title' => 'Niqobah',
            'position_x' => $niqobahX,
            'position_y' => $niqobahY,
        ];

        $nameData = $templateIdCardData + [
            'title' => 'Name',
            'position_x' => $nameX,
            'position_y' => $nameY,
        ];

        ComponentTemplateIdCard::where('id_template', $idTemplate)->delete();

        ComponentTemplateIdCard::create($photoData, $photoData);
        ComponentTemplateIdCard::create($niqobahData, $niqobahData);
        ComponentTemplateIdCard::create($nameData, $nameData);

        \DataPicker::activitas_log('menambahkan component card');

        return response()->json([
            'success' => true,
            'message' => 'berhasil tambah data',
            'data'    => 'success' 
        ], 200);
    }

}
