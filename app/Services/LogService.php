<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class LogService
{
    protected $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
    }

    public function getLogs()
    {
        if (!File::exists($this->logPath)) {
            return collect([]);
        }

        $content = File::get($this->logPath);
        $logs = [];

        // Patrón mejorado para detectar el inicio de cada entrada de log
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\Z)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $index => $match) {
            $datetime = Carbon::parse($match[1]);

            $logs[] = [
                'id' => $index + 1,
                'datetime' => $match[1],
                'datetime_formatted' => $datetime->format('d/m/Y H:i:s'),
                'environment' => strtoupper($match[2]),
                'level' => strtoupper($match[3]),
                'message' => trim($match[4]),
                'timestamp' => $datetime->timestamp,
            ];
        }

        // Ordenar por fecha descendente (más recientes primero)
        return collect($logs)->sortByDesc('timestamp')->values();
    }

    public function getLogById($id)
    {
        return $this->getLogs()->firstWhere('id', (int)$id);
    }

    public function clearLogs()
    {
        if (File::exists($this->logPath)) {
            File::put($this->logPath, '');
            return true;
        }
        return false;
    }

    public function getLogsByLevel($level = null)
    {
        $logs = $this->getLogs();

        if ($level) {
            return $logs->filter(function ($log) use ($level) {
                return $log['level'] === strtoupper($level);
            });
        }

        return $logs;
    }

    public function getLogStats()
    {
        $logs = $this->getLogs();

        return [
            'total' => $logs->count(),
            'error' => $logs->where('level', 'ERROR')->count(),
            'warning' => $logs->where('level', 'WARNING')->count(),
            'info' => $logs->where('level', 'INFO')->count(),
            'debug' => $logs->where('level', 'DEBUG')->count(),
            'critical' => $logs->where('level', 'CRITICAL')->count(),
        ];
    }
}
