@props(['user', 'avatarUrl'])

<div class="col app-chat-sidebar-left app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-sidebar-left">
  <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
    <div class="avatar avatar-xl avatar-online">
      <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle" />
    </div>
    <h5 class="mt-4 mb-0">{{ $user->username ?? 'User' }}</h5>
    <span>User</span>
    <div class="d-flex align-items-center mt-2">
      <i class="icon-base ri ri-check-line text-success me-1"></i><small class="text-success">Online</small>
    </div>
  </div>
  <div class="sidebar-body px-6 pb-6 pt-2">
    <div class="my-6">
      <p class="text-uppercase text-body-secondary mb-1">Profile photo</p>
      <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data">
        @csrf
        <div class="input-group input-group-sm">
          <input type="file" name="avatar" accept="image/*" class="form-control" required />
          <button type="submit" class="btn btn-primary">Upload</button>
        </div>
        @error('avatar')
          <small class="text-danger d-block mt-1">{{ $message }}</small>
        @enderror
        @if (session('status') === 'Profile photo updated.')
          <small class="text-success d-block mt-1">Photo updated.</small>
        @endif
      </form>
    </div>
    <div class="d-flex mt-6">
      <form method="POST" action="{{ route('logout') }}" class="w-100">
        @csrf
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center">
          Logout<i class="icon-base ri ri-logout-box-r-line icon-xs ms-1"></i>
        </button>
      </form>
    </div>
  </div>
</div>
