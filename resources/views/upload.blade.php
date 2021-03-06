<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 上述3个meta标签*必须*放在最前面，任何其他内容都*必须*跟随其后！ -->
    <title>Upload Large File</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim 和 Respond.js 是为了让 IE8 支持 HTML5 元素和媒体查询（media queries）功能 -->
    <!-- 警告：通过 file:// 协议（就是直接将 html 页面拖拽到浏览器中）访问页面时 Respond.js 不起作用 -->
    <!--[if lt IE 9]>
    <script src="https://cdn.jsdelivr.net/npm/html5shiv@3.7.3/dist/html5shiv.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/respond.js@1.4.2/dest/respond.min.js"></script>
    <![endif]-->
</head>
<body>

<div class="container" style="margin-top: 100px">
    <div class="form-group">
        <input type="file" id="upload-file">

        <br>
        <div class="progress progress-striped" id="progress">
            <div class="progress-bar progress-bar-info" role="progressbar"
                 aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"
                 style="width: 0%;">
                <span class="sr-only">0% 完成</span>
            </div>
        </div>
        <!--        <span class="help-block">0% 完成</span>-->

        <p id="upload-info"></p>

        <br>
        <button class="btn btn-primary" id="upload-btn">上传</button>
    </div>

</div>
<!-- jQuery (Bootstrap 的所有 JavaScript 插件都依赖 jQuery，所以必须放在前边) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@1.12.4/dist/jquery.min.js"></script>
<!-- 加载 Bootstrap 的所有 JavaScript 插件。你也可以根据需要只加载单个插件。 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
<script src="{{asset('js')}}/spark-md5.min.js"></script>
<script>
    let upload_url = '{{route('upload')}}';
    // var log=document.getElementById("log");
    let file_md5;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#upload-file").on("change", function(){
        let blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice,
            //file = this.files[0],
            file = $(this)[0].files[0],
            chunkSize = 2097152, // read in chunks of 2MB
            chunks = Math.ceil(file.size / chunkSize),
            currentChunk = 0,
            spark = new SparkMD5.ArrayBuffer(),
            frOnload = function(e){
                file_md5 = '';
                $("#upload-info").text('正在计算文件hash');

                // log.innerHTML+="\nread chunk number "+parseInt(currentChunk+1)+" of "+chunks;
                spark.append(e.target.result); // append array buffer
                currentChunk++;
                if (currentChunk < chunks) {
                    loadNext();
                }else{
                    // log.innerHTML+="\nfinished loading :)\n\ncomputed hash:\n"+spark.end()+"\n\nyou can select another file now!\n";
                    file_md5 = spark.end();
                    // console.log(file_md5);
                    $("#upload-info").text('文件hash为：'+file_md5);
                }
            },
            frOnerror = function () {
                // log.innerHTML+="\noops, something went wrong.";
                alert('读取文件失败');
                file_md5 = '';
                $("#upload-info").text('正在计算文件hash失败');
            };
        function loadNext() {
            let fileReader = new FileReader();
            fileReader.onload = frOnload;
            fileReader.onerror = frOnerror;
            let start = currentChunk * chunkSize,
                end = ((start + chunkSize) >= file.size) ? file.size : start + chunkSize;
            fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
        }
        // log.style.display="inline-block";
        // log.innerHTML="file name: "+file.name+" ("+file.size.toString().replace(/\B(?=(?:\d{3})+(?!\d))/g, ',')+" bytes)\n";

        $("#upload-info").html('');
        $("#progress .progress-bar-info").css('width', '0%');
        $("#progress .sr-only").text('0% 完成');
        $("#progress").addClass('active');

        loadNext();
    });

    $("#upload-btn").click(function(){
        if($("#upload-file")[0].files.length == 0){
            alert('请选择需要上传的文件');
            return false;
        }

        $("#upload-info").html('上传中...');
        $("#progress .progress-bar-info").css('width', '0%');
        $("#progress .sr-only").text('0% 完成');
        $("#progress").addClass('active');

        let file = $("#upload-file")[0].files[0];
        //let name = Math.random()+file.name;
        let name;
        if(file_md5){
            name = file_md5+':'+file.name;

            //尝试秒传
            $.ajax({
                url: upload_url+'?type=miao',
                type: "POST",
                data: {md5: file_md5, name: name},
                success: function (res) {
                    if(res.code == 200){
                        $("#progress .progress-bar-info").css('width', '100%');
                        $("#progress .sr-only").text('100% 完成');
                        $("#upload-info").html('秒传！上传完成！<a target="_blank" href="'+res.data.url+'">查看文件</a>');
                        $("#upload-info").data('completed', 'completed');
                        $("#progress").removeClass('active');
                    }else{
                        upload_file(file, name, file_md5);
                    }
                }
            });
        }else{
            name = Math.random()+':'+file.name;
            upload_file(file, name, '');
        }
    });

    function upload_file(file, name, file_md5){

        $("#upload-info").text('上传中...');

        let size = file.size;

        let success = 0;
        let percent = 0;

        const shardSize = 1024 * 1024; // 1MB
        let shardCount = Math.ceil(size/shardSize);

        $("#upload-info").data('completed', '');

        for(let i = 0; i < shardCount; i++){

            // if($("#upload-info").data('completed') == 'completed'){
            //     break;
            // }

            let start = i * shardSize,
                end = Math.min(size, start + shardSize);

            let form = new FormData();
            form.append('file', file.slice(start, end));
            form.append('size', end - start);
            form.append('name', name);
            form.append('total', shardCount);
            form.append('md5', file_md5);
            form.append('index', i);

            $.ajax({
                url: upload_url+'?type=shard',
                type: "POST",
                data: form,
                // async: false,     //是否异步上传，默认true
                processData: false, //很重要，告诉jquery不要对form进行处理
                contentType: false, //很重要，指定为false才能形成正确的Content-Type
                success: function (res) {
                    if(res.code == 200){
                        ++success;

                        percent = success/shardCount * 100;
                        console.log(percent);

                        $("#progress .progress-bar-info").css('width', percent+'%');
                        $("#progress .sr-only").text(percent+'% 完成');

                        if(success == shardCount){  // last process completed
                            $("#upload-info").text('合并文件中...');
                            $.ajax({
                                url: upload_url+'?type=merge',
                                type: "POST",
                                data: {name: name, size: size, total: shardCount, md5: file_md5},
                                success: function (res) {
                                    if(res.code == 200){
                                        $("#progress .progress-bar-info").css('width', '100%');
                                        $("#progress .sr-only").text('100% 完成');
                                        $("#upload-info").html('上传完成！<a target="_blank" href="'+res.data.url+'">查看文件</a>');
                                        $("#upload-info").data('completed', 'completed');
                                        $("#progress").removeClass('active');
                                    }else{
                                        alert(res.msg);
                                        //$("#upload-info").append('<p>shard '+i+' upload failed</p>');
                                        $("#progress").removeClass('active');
                                    }
                                }
                            });
                        }
                    }
                    // else if(res.code == 200){
                    //     $("#progress .progress-bar-info").css('width', '100%');
                    //     $("#progress .sr-only").text('100% 完成');
                    //     $("#upload-info").append('<a target="_blank" href="'+res.data.url+'">File url</a>');
                    //     $("#upload-info").data('completed', 'completed');
                    //     $("#progress").removeClass('active');
                    // }else if(res.code == 210){
                    //     $("#progress .progress-bar-info").css('width', '100%');
                    //     $("#progress .sr-only").text('100% 完成');
                    //     if($("#upload-info").data('completed') != 'completed'){
                    //         $("#upload-info").data('completed', 'completed');
                    //         $("#upload-info").append('<a target="_blank" href="'+res.data.url+'">秒传！ File url</a>');
                    //     }
                    //     $("#progress").removeClass('active');
                    // }
                    else{
                        alert(res.msg);
                        $("#upload-info").html('<p>shard '+i+' upload failed</p>');
                        $("#progress").removeClass('active');
                    }
                    console.log(res);
                }
            });
        }
    }
</script>
</body>
</html>