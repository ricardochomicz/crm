<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class OpportunityTerm extends Model
{
    const BASE_PATH = 'app/public';
    const DIR_TERMS = 'terms';
    const TERMS_PATH = self::BASE_PATH . '/' . self::DIR_TERMS;

    protected $fillable = ['file_name', 'opportunity'];

    public static function termsPath($opportunity)
    {
        $path = self::TERMS_PATH;
        return storage_path("{$path}/{$opportunity}");
    }

    public static function createTermFiles(int $opportunity, array $files): Collection
    {
        self::uploadFiles($opportunity, $files);
        $terms = self::createTermsModels($opportunity, $files);
        return new Collection($terms);
    }

    public static function uploadFiles($opportunity, array $files)
    {
        $dir = self::termsDir($opportunity);
        foreach ($files as $file) {
            $file->store($dir, ['disk' => 'public']);
        }
    }

    private static function createTermsModels($opportunity, array $files)
    {
        $terms = [];
        foreach ($files as $file) {
            $terms[] = self::create([
                'file_name' => $file->hashName(),
                'opportunity' => $opportunity
            ]);
        }
        return $terms;
    }


    public function getUrlTermAttribute()
    {
        $path = self::termsDir($this->opportunity_id);
        return asset("teste/{$path}/{$this->file_name}");
    }

    public static function termsDir($opportunity)
    {
        $dir = self::DIR_TERMS;
        return "{$dir}/{$opportunity}";

    }


    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
}
