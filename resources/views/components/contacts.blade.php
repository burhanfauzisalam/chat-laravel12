@props(['rooms', 'mqttTopic', 'avatarUrl'])

<div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
  <div class="sidebar-header px-5 border-bottom d-flex align-items-center">
    <div class="d-flex align-items-center me-6 me-lg-0">
      <div class="flex-shrink-0 avatar avatar-online me-4" data-bs-toggle="sidebar" data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
        <img class="user-avatar rounded-circle cursor-pointer" src="{{ $avatarUrl }}" alt="Avatar" />
      </div>
      <div class="flex-grow-1 input-group input-group-sm input-group-merge rounded-pill">
        <span class="input-group-text" id="basic-addon-search31"><i class="icon-base ri ri-search-line icon-20px"></i></span>
        <input type="text" class="form-control chat-search-input" placeholder="Search..." aria-label="Search..." aria-describedby="basic-addon-search31" />
      </div>
    </div>
    <i class="icon-base ri ri-close-line icon-lg cursor-pointer position-absolute top-50 end-0 translate-middle d-lg-none d-block" data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
  </div>
  <div class="sidebar-body">
    <ul class="list-unstyled chat-contact-list py-2 mb-0" id="chat-list">
      <li class="chat-contact-list-item chat-contact-list-item-title mt-0 d-flex justify-content-between align-items-center px-4">
        <h5 class="text-primary mb-0">Rooms</h5>
        <button
          type="button"
          class="btn btn-sm btn-icon btn-text-primary"
          data-bs-toggle="modal"
          data-bs-target="#addRoomModal"
          title="Add room"
        >
          <i class="icon-base ri ri-add-line icon-20px"></i>
        </button>
      </li>
      @forelse($rooms as $room)
        <li
          class="chat-contact-list-item mb-1 {{ $room->topic === $mqttTopic ? 'active' : '' }}"
          data-room-topic="{{ $room->topic }}"
          data-room-name="{{ $room->name }}"
        >
          @php
            $roomLinkParams = ['topic' => $room->topic];
            if (!empty($mqttTopic) && $mqttTopic !== $room->topic) {
                $roomLinkParams['mark_read'] = $mqttTopic;
            }
          @endphp
          <a class="d-flex align-items-center" href="{{ route('chat.index', $roomLinkParams) }}">
            <div class="flex-shrink-0 avatar avatar-online">
              <span class="avatar-initial rounded-circle bg-label-primary">{{ strtoupper(substr($room->name,0,2)) }}</span>
            </div>
            <div class="chat-contact-info flex-grow-1 ms-4">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="chat-contact-name text-truncate fw-normal m-0">
                  {{ $room->name }}
                  <span class="text-body-secondary">({{ $room->users_count ?? 0 }})</span>
                </h6>
                @if(($room->unread_count ?? 0) > 0)
                  <span
                    class="badge bg-label-primary rounded-pill ms-2 room-unread-badge"
                    data-room-topic="{{ $room->topic }}"
                  >
                    {{ $room->unread_count }}
                  </span>
                @endif
              </div>
              <small class="chat-contact-status text-truncate">{{ $room->topic }}</small>
            </div>
          </a>
        </li>
      @empty
        <li class="chat-contact-list-item chat-list-item-0">
          <h6 class="text-body-secondary mb-0">No Rooms Found</h6>
        </li>
      @endforelse
    </ul>
  </div>
</div>
