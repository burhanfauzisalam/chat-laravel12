@props(['activeRoom'])

<div class="col app-chat-sidebar-right app-sidebar overflow-hidden" id="app-chat-sidebar-right">
  <div class="sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
    @if (isset($activeRoom))
      <div class="avatar avatar-xl avatar-online chat-sidebar-avatar">
        <span class="avatar-initial rounded-circle bg-label-primary">
          {{ strtoupper(substr($activeRoom->name, 0, 2)) }}
        </span>
      </div>
      <h5 class="mt-4 mb-0">{{ $activeRoom->name }}</h5>
      <span class="text-body-secondary">
        {{ $activeRoom->topic }}
      </span>
      <span class="text-body-secondary small">
        {{ $activeRoom->users->count() }} member{{ $activeRoom->users->count() === 1 ? '' : 's' }}
      </span>
    @else
      <h5 class="mt-4 mb-0">Room info</h5>
      <span class="text-body-secondary small">No room selected</span>
    @endif
    <i
      class="icon-base ri ri-close-line icon-24px cursor-pointer close-sidebar d-block"
      data-bs-toggle="sidebar"
      data-overlay
      data-target="#app-chat-sidebar-right"
    ></i>
  </div>
  <div class="sidebar-body p-6 pt-0">
    <div class="my-6">
      <p class="text-uppercase mb-1 text-body-secondary">Members</p>
      <ul class="list-unstyled d-grid gap-3 mb-0 py-2 text-heading">
        @if (isset($activeRoom) && $activeRoom->users->count())
          @foreach ($activeRoom->users as $member)
            <li class="d-flex align-items-center">
              <div class="avatar avatar-sm">
                <span class="avatar-initial rounded-circle bg-label-primary">
                  {{ strtoupper(substr($member->username, 0, 2)) }}
                </span>
              </div>
              <div class="ms-3">
                <div class="fw-medium">{{ $member->username }}</div>
              </div>
            </li>
          @endforeach
        @else
          <li class="text-body-secondary">No members in this room.</li>
        @endif
      </ul>
    </div>

    @if (isset($activeRoom))
      @php
        $inviteUrl = route('rooms.join', $activeRoom);
      @endphp
      <div class="my-6">
        <p class="text-uppercase mb-1 text-body-secondary">Invite link</p>
        <button
          type="button"
          class="btn btn-sm btn-primary"
          id="copy-invite-link-btn"
          data-invite-url="{{ $inviteUrl }}"
        >
          Copy link
        </button>
        <small class="text-body-secondary d-block mt-1 d-none" id="copy-invite-link-feedback">
          Link copied
        </small>
      </div>
      <div class="my-6">
        <p class="text-uppercase mb-1 text-body-secondary">Actions</p>
        <form method="POST" action="{{ route('rooms.leave', $activeRoom) }}" class="leave-room-form">
          @csrf
          <button type="submit" class="btn btn-outline-danger w-100 leave-room-btn">
            Leave room
          </button>
        </form>
      </div>
    @endif
  </div>
</div>
