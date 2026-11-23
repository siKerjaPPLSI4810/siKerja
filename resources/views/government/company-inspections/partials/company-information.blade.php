<section class="card mb-4" aria-labelledby="company-information-heading">
    <div class="card-header bg-white"><h2 id="company-information-heading" class="h6 mb-0">Identitas perusahaan</h2></div>
    <div class="card-body">
        <p class="small text-muted">Data perusahaan hanya dapat dibaca pada halaman ini.</p>
        <dl class="row mb-0 text-break">
            @foreach ([
                'Nama perusahaan' => $company->company_name,
                'NPWP' => $company->npwp,
                'Penanggung jawab' => $company->user?->name,
                'Email' => $company->user?->email,
                'Telepon' => $company->user?->phone,
                'Alamat' => $company->address,
                'Kota / kabupaten' => $company->city,
                'Provinsi' => $company->province,
                'Website' => $company->website,
            ] as $label => $value)
                <dt class="col-sm-5">{{ $label }}</dt><dd class="col-sm-7">{{ filled($value) ? $value : 'Belum tersedia' }}</dd>
            @endforeach
        </dl>
    </div>
</section>
