<?php


namespace App\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function showUpload(){
        return view('upload');
    }

    public function upload(Request $request){
        $path = storage_path('app/public/uploads');
        if(!is_dir($path)){
            Storage::makeDirectory('public/uploads');
        }

        $name = $request->input('name');
        $md5 = $request->input('md5');
        $type = $request->input('type');
        if ($type == 'miao') {
            if (file_exists($path . '/' . $name) && md5_file($path . '/' . $name) === $md5) {
                // 文件已存在，妙传
                return $this->echoJson(200, 'ok', [
                    'url' => 'storage/uploads/' . $name,
                ]);
            }
        } else if ($type == 'shard') {
            $file = $_FILES['file'];

            $total = $request->input('total');
            $index = $request->input('index');
            $size = $request->input('size');

            $dst_file = $path . '/' . $name . '-' . $total . ':' . $index;
            if ($file["error"] > 0) {
                return $this->echoJson(400, $file["error"]);
            } else {
                $res = move_uploaded_file($file['tmp_name'], $dst_file);
                if ($res) {
                    file_put_contents($dst_file . '.info', $size);
                    return $this->echoJson(200, 'shard ok');
                } else {
                    return $this->echoJson(400, 'shard move_uploaded_file error');
                }
            }
        } else if ($type == 'merge') {
            $size = $request->input('size');
            $total = $request->input('total');
            $msg = '';
            if ($this->mergeFile($path . '/' . $name, $total, $msg)) {
                return $this->echoJson(200, 'ok', [
                    'url' => 'storage/uploads/' . $name,
                ]);
            } else {
                return $this->echoJson(400, $msg);
            }
        }
    }

    protected function mergeFile($name, $total, &$msg){
        for ($i = 0; $i < $total; $i++) {
            if (!file_exists($name . '-' . $total . ':' . $i . '.info') || !file_exists($name . '-' . $total . ':' . $i)) {
                $msg = "shard error $i";
                return false;
            } else if (filesize($name . '-' . $total . ':' . $i) != file_get_contents($name . '-' . $total . ':' . $i . '.info')) {
                $msg = "shard size error $i";
                return false;
            }
        }

        @unlink($name);
        if (file_exists($name . '.lock')) {
            $msg = 'on lock';
            return false;
        }
        touch($name . '.lock');
        $file = fopen($name, 'a+');
        for ($i = 0; $i < $total; $i++) {
            $shardFile = fopen($name . '-' . $total . ':' . $i, 'r');
            $shardData = fread($shardFile, filesize($name . '-' . $total . ':' . $i));
            fwrite($file, $shardData);
            fclose($shardFile);
            unlink($name . '-' . $total . ':' . $i);
            unlink($name . '-' . $total . ':' . $i . '.info');
        }
        fclose($file);
        unlink($name . '.lock');

        return true;
    }

    protected function echoJson($code, $msg = 'ok', $data = []){
        return JsonResponse::create([
            'code' => $code,
            'msg' => $msg,
            'data' => (object)$data
        ]);
    }
}