<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FaceRecognitionController extends Controller
{
    // Titik lokasi kantor
    private float $officeLat = -6.200000;
    private float $officeLng = 106.816666;
    private int $maxRadius = 100; // meter

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],
        ]);

        $user = Auth::user();

        // Simpan foto wajah ke storage
        $path = "uploads/faces/{$user->id}_" . time() . ".png";
        $data = preg_replace('#^data:image/[^;]+;base64,#', '', $validated['image']);
        Storage::disk('public')->put($path, base64_decode($data));

        // Jalankan Python untuk ekstraksi descriptor
        $descriptor = $this->runPythonRecognition('extract', storage_path("app/public/{$path}"));

        if (!$descriptor) {
            return response()->json(['ok' => false, 'message' => 'Face not detected'], 422);
        }

        $user->face_descriptor = json_encode($descriptor);
        $user->save();

        return response()->json(['ok' => true, 'message' => 'Face enrolled']);
    }

    public function attendance(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'type' => ['nullable', 'in:in,out']
        ]);

        $path = "uploads/attendances/" . Auth::id() . "_" . time() . ".png";
        $data = preg_replace('#^data:image/[^;]+;base64,#', '', $validated['image']);
        Storage::disk('public')->put($path, base64_decode($data));

        // Jalankan Python untuk bandingkan wajah
        $matchUserId = $this->runPythonRecognition('match', storage_path("app/public/{$path}"));

        if ($matchUserId != Auth::id()) {
            return response()->json(['ok' => false, 'message' => 'Face not recognized'], 422);
        }

        // Validasi lokasi
        $distance = $this->distance($this->officeLat, $this->officeLng, $validated['latitude'], $validated['longitude']);
        if ($distance > $this->maxRadius) {
            return response()->json(['ok' => false, 'message' => 'Outside allowed radius'], 422);
        }

        Attendance::create([
            'user_id' => Auth::id(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'image_path' => $path,
            'type' => $validated['type'] ?? 'in',
        ]);

        return response()->json(['ok' => true, 'message' => 'Attendance recorded']);
    }

    private function runPythonRecognition(string $mode, string $imagePath): mixed
    {
        $process = new Process(['python3', base_path('scripts/recognize_face.py'), $mode, $imagePath, Auth::id()]);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return json_decode($process->getOutput(), true);
    }

    private function distance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c; // in meters
    }
}
