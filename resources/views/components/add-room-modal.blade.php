<div class="modal fade" id="addRoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add room</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="{{ route('rooms.store') }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="room_name">Room name</label>
            <input
              type="text"
              id="room_name"
              name="room_name"
              class="form-control"
              placeholder="Room name"
              value="{{ old('room_name') }}"
              required
            />
            @error('room_name')
              <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="room_topic">Topic</label>
            <input
              type="text"
              id="room_topic"
              name="room_topic"
              class="form-control"
              placeholder="Contoh: chat/support"
              value="{{ old('room_topic') }}"
              required
            />
            @error('room_topic')
              <small class="text-danger d-block mt-1">{{ $message }}</small>
            @enderror
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-text-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create room</button>
        </div>
      </form>
    </div>
  </div>
</div>
