function seg(outputDir)
% #eml
% clear;
[signal,Fs]=wavread([outputDir, 'test.wav']);
signal_length=length(signal);
signal_total_time=signal_length/Fs;
threshold=0.04;
window_length=Fs/2;
mar=100;
mov=ones(1,window_length)/window_length;
d=[1,-1];
x=abs(signal.^2);
x=x/max(x);
x=conv(x,mov);
x=x/max(x);
x=conv(x,d);
x=x/max(x);
x=abs(x);
x=conv(x,mov);
x=x/max(x);
%figure;
%plot(x);
count=0;
find_start=0;
start=1;
index=1;
finish=signal_length;
error_case=0;
for i=1:length(x)
    if((x(i)>threshold)&&(find_start==0))
        error_case=0;
        find_start=1;
        start=i;
    elseif((x(i)<threshold)&&(find_start==1))
        finish=i;
        find_start=0;
		be=start-mar-window_length;
		en=finish+mar-window_length;
        count=count+1;
		if(en>signal_length)
			en=signal_length;
            error_case=1;
        end
        if(be<0)
            be=1;
            error_case=1;
        end
        if(error_case==1)
            count=count-1;
            continue;
        else
            a(index)=be/Fs;
            a(index+1)=en/Fs;
            index=index+2;
            wavwrite(signal(be:en),Fs,[outputDir, 'output',int2str(count),'.wav']);
        end
    end
end
if(count==0)
    wavwrite(signal(1:signal_length),Fs,[outputDir, 'output1.wav']);
    a(1)=0;
    a(2)=signal_length/Fs;
end
dlmwrite([outputDir, 'seg_time.txt'],a);
end
