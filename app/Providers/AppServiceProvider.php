<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Tag;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Setting;
use App\Models\Language;
use App\Models\Priority;
use Faker\Extension\Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Services\CustomPathGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PathGenerator::class, CustomPathGenerator::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();
        // View::composer('modals', function ($view) {
        //     $users = User::all();
        //     $clients = Client::all();

        //     // Pass users and clients data to the view
        //     $view->with('toSelectWorkspaceUsers', $users)->with('toSelectWorkspaceClients', $clients);
        // });

        try {
            DB::connection()->getPdo();

            // The table exists in the database
            $languages = Language::all();
            // $statuses = Status::all();
            // $tags = Tag::all();
            // $priorities = Priority::all();
            $general_settings = get_settings('general_settings');

            $general_settings['full_logo'] = !isset($general_settings['full_logo']) || empty($general_settings['full_logo']) ? 'storage/logos/default_full_logo.png' : 'storage/' . $general_settings['full_logo'];
            $general_settings['half_logo'] = !isset($general_settings['half_logo']) || empty($general_settings['half_logo']) ? 'storage/logos/default_half_logo.png' : 'storage/' . $general_settings['half_logo'];
            $general_settings['favicon'] = !isset($general_settings['favicon']) || empty($general_settings['favicon']) ? 'storage/logos/default_favicon.png' : 'storage/' . $general_settings['favicon'];
            $general_settings['footer_logo'] = !isset($general_settings['footer_logo']) || empty($general_settings['footer_logo']) ? 'storage/logos/footer_logo.png' : 'storage/' . $general_settings['footer_logo'];

            $general_settings['company_title'] = $general_settings['company_title'] ?? 'Taskify - SaaS';
            $general_settings['currency_symbol'] = $general_settings['currency_symbol'] ?? '₹';
            $general_settings['currency_full_form'] = $general_settings['currency_full_form'] ?? 'Indian Rupee';
            $general_settings['currency_code'] = $general_settings['currency_code'] ?? 'INR';

            $pusher_settings = get_settings('pusher_settings');
            $pusher_settings['pusher_app_id'] = $pusher_settings['pusher_app_id'] ?? '';
            $pusher_settings['pusher_app_key'] = $pusher_settings['pusher_app_key'] ?? '';
            $pusher_settings['pusher_app_secret'] = $pusher_settings['pusher_app_secret'] ?? '';
            $pusher_settings['pusher_app_cluster'] = $pusher_settings['pusher_app_cluster'] ?? '';

            $email_settings = get_settings('email_settings');
            $email_settings['email'] =  $email_settings['email'] ?? '';
            $email_settings['password'] = $email_settings['password'] ?? '';
            $email_settings['smtp_host'] = $email_settings['smtp_host'] ?? '';
            $email_settings['smtp_port'] = $email_settings['smtp_port'] ?? '';
            $email_settings['email_content_type'] = $email_settings['email_content_type'] ?? '';
            $email_settings['smtp_encryption'] = $email_settings['smtp_encryption'] ?? '';

            $media_storage_settings = get_settings('media_storage_settings');
            $media_storage_settings['media_storage_type'] =  $media_storage_settings['media_storage_type'] ?? '';
            $media_storage_settings['s3_key'] =  $media_storage_settings['s3_key'] ?? '';
            $media_storage_settings['s3_secret'] =  $media_storage_settings['s3_secret'] ?? '';
            $media_storage_settings['s3_region'] =  $media_storage_settings['s3_region'] ?? '';
            $media_storage_settings['s3_bucket'] =  $media_storage_settings['s3_bucket'] ?? '';


            $date_format = $general_settings['date_format'] = $general_settings['date_format'] ?? 'DD-MM-YYYY|d-m-Y';
            $date_format = explode('|', $date_format);
            $js_date_format = $date_format[0];
            $php_date_format = $date_format[1];
            $this->app->singleton('php_date_format', function () use ($php_date_format) {
                return $php_date_format;
            });

            view()->composer('*', function ($view) {
                if (Auth::check()) {
                    $adminID = getAdminIdByUserRole();
                    // dd($adminID);
                    $statuses = Status::where('admin_id', $adminID)->get();
                    $tags = Tag::where('admin_id', $adminID)->get();;
                    $priorities = Priority::where('admin_id', $adminID)->get();
                    $view->with(['statuses' => $statuses, 'tags' => $tags, 'priorities' => $priorities]);
                }
            });
            $data = ['general_settings' => $general_settings, 'email_settings' => $email_settings, 'pusher_settings' => $pusher_settings, 'media_storage_settings' => $media_storage_settings, 'languages' => $languages, 'js_date_format' => $js_date_format, 'php_date_format' => $php_date_format,];
            view()->share($data);

            config()->set('app.timezone', $general_settings['timezone']);

            config()->set('chatify.name', $general_settings['company_title']);
            config()->set('chatify.pusher.key', $pusher_settings['pusher_app_key']);
            config()->set('chatify.pusher.secret', $pusher_settings['pusher_app_secret']);
            config()->set('chatify.pusher.app_id', $pusher_settings['pusher_app_id']);
            config()->set('chatify.pusher.options.cluster', $pusher_settings['pusher_app_cluster']);

            config()->set('mail.mailers.smtp.host', $email_settings['smtp_host']);
            config()->set('mail.mailers.smtp.port', $email_settings['smtp_port']);
            config()->set('mail.mailers.smtp.encryption', $email_settings['smtp_encryption']);
            config()->set('mail.mailers.smtp.username', $email_settings['email']);
            config()->set('mail.mailers.smtp.password', $email_settings['password']);

            config()->set('mail.from.name', $general_settings['company_title']);
            config()->set('mail.from.address', $email_settings['email']);


            config()->set('filesystems.disks.s3.key', $media_storage_settings['s3_key']);
            config()->set('filesystems.disks.s3.secret', $media_storage_settings['s3_secret']);
            config()->set('filesystems.disks.s3.region', $media_storage_settings['s3_region']);
            config()->set('filesystems.disks.s3.bucket', $media_storage_settings['s3_bucket']);
        } catch (\Exception $e) {
        }
    }
}