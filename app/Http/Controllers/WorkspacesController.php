<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Workspace;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WorkspacesController extends Controller
{
    protected $workspace;
    protected $user;
    public function __construct()
    {

        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(session()->get('workspace_id'));
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }
    public function index()
    {
        $workspaces = Workspace::all();
        $adminId = getAdminIdByUserRole();
        $admin = Admin::with('user', 'teamMembers.user')->find($adminId);

        $users = $admin->teamMembers;
        $toSelectWorkspaceUsers = $admin->teamMembers;
        $clients = Client::where('admin_id', $adminId)->get();
        $toSelectWorkspaceClients = Client::where('admin_id', $adminId)->get();
        return view('workspaces.workspaces', compact('workspaces', 'users', 'clients', 'admin', 'toSelectWorkspaceClients', 'toSelectWorkspaceUsers'));
    }
    public function create()
    {
        $adminId = getAdminIdByUserRole();
        $admin = Admin::with('user', 'teamMembers.user')->find($adminId);

        $users = User::all();
        $clients = Client::where('admin_id', $adminId)->get();
        $auth_user = $this->user;

        return view('workspaces.create_workspace', compact('users', 'clients', 'auth_user', 'admin'));
    }
    public function store(Request $request)
    {

        $adminId = null;
        if (Auth::guard('web')->check() && $this->user->hasRole('admin')) {
            $admin = Admin::where('user_id', $this->user->id)->first();
            if ($admin) {
                $adminId = $admin->id;
            }
        }

        $formFields = $request->validate([
            'title' => ['required']
        ]);

        $formFields['user_id'] = $this->user->id;
        $formFields['admin_id'] = $adminId;
        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];

        // Set creator as a participant automatically

        if (Auth::guard('client')->check() && !in_array($this->user->id, $clientIds)) {
            array_splice($clientIds, 0, 0, $this->user->id);
        } else if (Auth::guard('web')->check() && !in_array($this->user->id, $userIds)) {
            array_splice($userIds, 0, 0, $this->user->id);
        }
        $primaryWorkspace = isAdminOrHasAllDataAccess() && $request->input('primaryWorkspace') && $request->filled('primaryWorkspace') && $request->input('primaryWorkspace') == 'on' ? 1 : 0;

        $formFields['is_primary'] = $primaryWorkspace;

        // Create new workspace

        $new_workspace = Workspace::create($formFields);
        if ($primaryWorkspace) {
            // Set all other workspaces to non-primary
            Workspace::where('id', '!=', $new_workspace->id)->update(['is_primary' => 0]);
        }
        $workspace_id = $new_workspace->id;
        if ($this->workspace == null) {
            session()->put('workspace_id', $workspace_id);
        }
        // Attach users and clients to the workspace
        $workspace = Workspace::find($workspace_id);
        $workspace->users()->attach($userIds, ['admin_id' => $adminId]);
        $workspace->clients()->attach($clientIds, ['admin_id' => $adminId]);

        //Create activity log
        $activityLogData = [
            'workspace_id' => $workspace_id,
            'admin_id' => $adminId,
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'type_id' => $workspace_id,
            'type' => 'workspace',
            'activity' => 'created',
            'message' => $this->user->name . ' created workspace ' . $new_workspace->title,
        ];

        ActivityLog::create($activityLogData);
        $notification_data = [
            'type' => 'workspace',
            'type_id' => $workspace_id,
            'type_title' => $workspace->title,
            'action' => 'assigned',
            'title' => 'Added in a workspace',
            'message' => $this->user->first_name . ' ' . $this->user->last_name . ' added you in workspace: ' . $workspace->title . ', ID #' . $workspace_id . '.'

        ];

        // Combine user and client IDs for notification recipients
        $recipients = array_merge(
            array_map(function ($userId) {
                return 'u_' . $userId;
            }, $userIds),
            array_map(function ($clientId) {
                return 'c_' . $clientId;
            }, $clientIds)
        );

        // Process notifications
        processNotifications($notification_data, $recipients);
        Session::flash('message', 'Workspace created successfully.');
        return response()->json(['error' => false, 'message' => 'Workspace created successfully.']);
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";

        $workspaces = isAdminOrHasAllDataAccess() ? $this->workspace : $this->user->workspaces();
        // dd(getAdminIDByUserRole());


        if ($user_id) {
            $user = User::find($user_id);
            $workspaces = $user->workspaces();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $workspaces = $client->workspaces();
        }
        $workspaces = $workspaces->when($search, function ($query) use ($search) {
            return $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        });
        $workspaces->where('workspaces.admin_id', getAdminIDByUserRole());
        $totalworkspaces = $workspaces->count();

        $canCreate = checkPermission('create_workspaces');
        $canEdit = checkPermission('edit_workspaces');
        $canDelete = checkPermission('delete_workspaces');

        $workspaces = $workspaces->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($workspace) use ($canEdit, $canDelete, $canCreate) {

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-workspace" data-id="' . $workspace->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $workspace->id . '" data-type="workspaces">' .
                '<i class="bx bx-trash text-danger mx-1"></i>' .
                '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $workspace->id . '" data-title="' . $workspace->title . '" data-type="workspaces" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                $actions = $actions ?: '-';

                $userHtml = '';
                if (!empty($workspace->users) && count($workspace->users) > 0) {
                    $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($workspace->users as $user) {
                        $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='/users/profile/{$user->id}' target='_blank' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $userHtml .= '</ul>';
                } else {
                    $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                $clientHtml = '';
                if (!empty($workspace->clients) && count($workspace->clients) > 0) {
                    $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($workspace->clients as $client) {
                        $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='/clients/profile/{$client->id}' target='_blank' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $clientHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $clientHtml .= '</ul>';
                } else {
                    $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $clientHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }
                return [
                    'id' => $workspace->id,
                'title' => '<a href="workspaces/switch/' . $workspace->id . '">' . $workspace->title . '</a>' . ($workspace->is_primary ? ' <span class="badge bg-success">' . get_label('primary', 'Primary') . '</span>' : ''),
                'users' => $userHtml,
                'clients' => $clientHtml,
                'created_at' => format_date($workspace->created_at, true),
                'updated_at' => format_date($workspace->updated_at, true),
                'actions' => $actions
                ];
            });

        return response()->json([
            "rows" => $workspaces->items(),
            "total" => $totalworkspaces,
        ]);
    }


    public function edit($id)
    {
        $workspace = Workspace::findOrFail($id);
        $admin = Admin::with('user', 'teamMembers.user')->find(getAdminIdByUserRole());
        $clients = Client::where('admin_id', getAdminIdByUserRole())->get();
        return view('workspaces.update_workspace', compact('workspace', 'clients', 'admin'));
    }

    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required|exists:workspaces,id',
            'title' => ['required']
        ]);
        $id = $request->input('id');
        $workspace = Workspace::findOrFail($id);
        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];

        // Set creator as a participant automatically
        if (User::where('id', $workspace->user_id)->exists() && !in_array($workspace->user_id, $userIds)) {
            array_splice($userIds, 0, 0, $workspace->user_id);
        } elseif (Client::where('id', $workspace->user_id)->exists() && !in_array($workspace->user_id, $clientIds)) {
            array_splice($clientIds, 0, 0, $workspace->user_id);
        }
        $existingUserIds = $workspace->users->pluck('id')->toArray();
        $existingClientIds = $workspace->clients->pluck('id')->toArray();
        if (isAdminOrHasAllDataAccess()) {
            if ($request->has('primaryWorkspace')) {
                $primaryWorkspace = $request->boolean('primaryWorkspace', false) ? 1 : 0;
                $formFields['is_primary'] = $primaryWorkspace;
            } else {
                $primaryWorkspace = 0;
            }
        } else {
            $primaryWorkspace = $workspace->is_primary;
        }

        $workspace->update($formFields);
        if ($primaryWorkspace) {
            // Set all other workspaces to non-primary
            Workspace::where('id', '!=', $workspace->id)->update(['is_primary' => 0]);
        }
        $workspace->users()->sync($userIds);
        $workspace->clients()->sync($clientIds);
        $userIds = array_diff($userIds, $existingUserIds);
        $clientIds = array_diff($clientIds, $existingClientIds);
        // Prepare notification data
        $notification_data = [
            'type' => 'workspace',
            'type_id' => $id,
            'type_title' => $workspace->title,
            'action' => 'assigned',
            'title' => 'Added in a workspace',
            'message' => $this->user->first_name . ' ' . $this->user->last_name . ' added you in workspace: ' . $workspace->title . ', ID #' . $id . '.'
        ];

        // Combine user and client IDs for notification recipients
        $recipients = array_merge(
            array_map(function ($userId) {
                return 'u_' . $userId;
            }, $userIds),
            array_map(function ($clientId) {
                return 'c_' . $clientId;
            }, $clientIds)
        );

        // Process notifications
        processNotifications($notification_data, $recipients);
        Session::flash('message', 'Workspace updated successfully.');
        return response()->json(['error' => false, 'id' => $id, 'message' => 'Workspace updated successfully.']);
    }

    public function destroy($id)
    {
        // dd($id);

        if ($this->workspace->id != $id) {
            $response = DeletionService::delete(Workspace::class, $id, 'Workspace');
            return $response;
        } else {
            return response()->json(['error' => true, 'message' => 'Current workspace couldn\'t deleted.']);
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:workspaces,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedWorkspaces = [];
        $deletedWorkspaceTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $workspace = Workspace::find($id);
            if ($workspace) {
                $deletedWorkspaces[] = $id;
                $deletedWorkspaceTitles[] = $workspace->title;
                DeletionService::delete(Workspace::class, $id, 'Workspace');
            }
        }

        return response()->json(['error' => false, 'message' => 'Workspace(s) deleted successfully.', 'id' => $deletedWorkspaces, 'titles' => $deletedWorkspaceTitles]);
    }

    public function switch($id)
    {
        if (Workspace::findOrFail($id)) {
            session()->put('workspace_id', $id);
            return back()->with('message', 'Workspace changed successfully.');
        } else {
            return back()->with('error', 'Workspace not found.');
        }
    }

    public function remove_participant()
    {
        $workspace = Workspace::findOrFail(session()->get('workspace_id'));
        if ($this->user->hasRole('client')) {
            $workspace->clients()->detach($this->user->id);
        } else {
            $workspace->users()->detach($this->user->id);
        }
        $workspace_id = isset($this->user->workspaces[0]['id']) && !empty($this->user->workspaces[0]['id']) ? $this->user->workspaces[0]['id'] : 0;
        $data = ['workspace_id' => $workspace_id];
        session()->put($data);
        Session::flash('message', 'Removed from workspace successfully.');
        return response()->json(['error' => false]);
    }

    public function duplicate($id)
    {
        // Define the related tables for this workspace
        $relatedTables = ['users', 'clients']; // Include related tables as needed

        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Workspace::class, $id, $relatedTables, $title);
        $workspace = Workspace::find($duplicate->id);
        $workspace->update(['is_primary' => 0]);
        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Workspace duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Workspace duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Workspace duplicated successfully.', 'id' => $id]);
    }
    public function get($id)
    {
        $workspace = Workspace::with('users', 'clients')->findOrFail($id);

        return response()->json(['error' => false, 'workspace' => $workspace]);
    }
}
