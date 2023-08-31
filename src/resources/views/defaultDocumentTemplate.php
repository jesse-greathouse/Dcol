<div    class="cta-banner {{ $class_1 }}" >

    <p  class="cta" >Download the document</p>

    <a  class="download-link" 
        href="{{ $document_url }}" 
        download="{{ $file_name }}"
    >
        <img    class="download-icon" 
                src="https://larata.media/wp-content/uploads/2023/08/pdf-icon-transparent.png" 
                alt="Download this document">

        <p  class="blurb" >
            {{ $blurb }}
        </p>
    </a>
</div>

<div class="document-writeup" >

    <h2>{{ $focus_keyphrase }}</h2>

    {!! $html_writeup !!}
    
</div>
