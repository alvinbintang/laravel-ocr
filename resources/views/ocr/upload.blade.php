<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upload PDF - Laravel OCR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-gray-800">Laravel OCR</h1>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Alert Messages -->
                @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
                @endif

                @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h2 class="text-2xl font-bold mb-4">Upload PDF</h2>

                        <form action="{{ route('ocr.extract') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Pilih file PDF (maksimal 20MB)
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="pdf" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                <span>Upload a file</span>
                                                <input id="pdf" name="pdf" type="file" accept=".pdf" class="sr-only" required>
                                            </label>
                                            <p class="pl-1">atau drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PDF hingga 20MB</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Upload dan Proses
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($ocrResults->isNotEmpty())
                <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-semibold mb-4">Riwayat Pemrosesan</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($ocrResults as $result)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $result->filename }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($result->status === 'pending')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Menunggu
                                            </span>
                                            @elseif ($result->status === 'processing')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Sedang Diproses
                                            </span>
                                            @elseif ($result->status === 'done')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Selesai
                                            </span>
                                            @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Error
                                            </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $result->created_at->format('d M Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if ($result->status === 'awaiting_selection' || $result->status === 'done')
                                            <a href="{{ route('ocr.preview', $result->id) }}" class="text-blue-600 hover:text-blue-900">Pilih Area</a>
                                            @elseif ($result->status === 'pending' || $result->status === 'processing')
                                            <a href="{{ route('ocr.status', $result->id) }}" class="text-blue-600 hover:text-blue-900">Cek Status</a>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </main>
    </div>

    <script>
        // Drag and drop functionality
        const dropZone = document.querySelector('form');
        const fileInput = document.querySelector('input[type="file"]');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-blue-500');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-blue-500');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html>
