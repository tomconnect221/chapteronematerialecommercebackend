<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    /**
     * Upload product images
     */
    public function uploadProductImages(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'images.required' => 'At least one image is required.',
            'images.max' => 'Cannot upload more than 10 images.',
            'images.*.required' => 'Each image file is required.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'images.*.max' => 'Each image cannot exceed 2MB.',
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            $filename = 'products/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public', $filename);
            $uploadedImages[] = Storage::url($filename);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'images' => $uploadedImages,
                'count' => count($uploadedImages)
            ]
        ]);
    }

    /**
     * Upload single image
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'folder' => 'nullable|string|max:50',
        ], [
            'image.required' => 'Image file is required.',
            'image.image' => 'File must be an image.',
            'image.mimes' => 'Image must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'image.max' => 'Image cannot exceed 2MB.',
            'folder.max' => 'Folder name cannot exceed 50 characters.',
        ]);

        $folder = $request->get('folder', 'general');
        $filename = $folder . '/' . Str::uuid() . '.' . $request->file('image')->getClientOriginalExtension();
        $path = $request->file('image')->storeAs('public', $filename);
        $url = Storage::url($filename);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => [
                'url' => $url,
                'filename' => $filename
            ]
        ]);
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
        ], [
            'filename.required' => 'Filename is required.',
        ]);

        $filename = $request->filename;
        
        if (Storage::exists('public/' . $filename)) {
            Storage::delete('public/' . $filename);
            
            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found'
        ], 404);
    }

    /**
     * Get file info
     */
    public function getFileInfo(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
        ], [
            'filename.required' => 'Filename is required.',
        ]);

        $filename = $request->filename;
        
        if (Storage::exists('public/' . $filename)) {
            $fileInfo = [
                'filename' => $filename,
                'url' => Storage::url($filename),
                'size' => Storage::size('public/' . $filename),
                'last_modified' => Storage::lastModified('public/' . $filename),
                'mime_type' => Storage::mimeType('public/' . $filename),
            ];

            return response()->json([
                'success' => true,
                'data' => $fileInfo
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found'
        ], 404);
    }
}
