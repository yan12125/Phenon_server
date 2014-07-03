import json
import subprocess
import math
import datetime
import time

def gen_filter(filter_name, param):
    param_str = []
    for item, value in param.items():
        param_str.append(item + '=' + str(value))
    return filter_name + '=' + ':'.join(param_str)

def gen_between(start, end):
   return '\'between(t, %f, %f)\'' % (start, end)

def run(config_file, input_file, output_file, creation_time):
    inputs = []
    toffsets = {}

    ffprobe_cmd = [ 'ffprobe', '-show_streams', '-print_format', 'json', '-loglevel', 'quiet', input_file ]
    video_info = json.loads(subprocess.Popen(ffprobe_cmd, stdout=subprocess.PIPE).communicate()[0].decode('utf-8'))
    duration = float(video_info['streams'][0]['duration'])
    start_time = datetime.datetime.strptime(creation_time, '%Y-%m-%d %H:%M:%S')

    config = json.load(open(config_file))
    filters = []
    overlay_filters = []
    fps = 25

    for item in config['texts']:
        if 'fade_in' in item:
            fade_in = item['fade_in']
            del item['fade_in']
        else:
            fade_in = 0
        if 'fade_out' in item:
            fade_out = item['fade_out']
            del item['fade_out']
        else:
            fade_out = 0

        if 'is_timestamp' in item:
            del item['is_timestamp']
            for i in range(0, math.ceil(duration)):
                item['enable'] = gen_between(i, i+1)
                item['text'] = datetime.datetime.strftime(start_time + datetime.timedelta(0, i), '%H\\\\:%M\\\\:%S')
                print(item['text'])
                filters.append(gen_filter('drawtext', item))
            continue

        start = item['start']
        end = item['end']
        orig_color = item['fontcolor']
        del item['start']
        del item['end']
        # fade in
        n = fade_in * fps
        for i in range(0, n):
            item['enable'] = gen_between(start + fade_in * i / n, start + fade_in * (i+1) / n)
            item['fontcolor'] = orig_color + '@' + str(i / n)
            filters.append(gen_filter('drawtext', item))
        # middle interval
        item['fontcolor'] = orig_color
        item['enable'] = gen_between(start + fade_in, end - fade_out)
        filters.append(gen_filter('drawtext', item))
        # fade out
        n = fade_out * fps
        for i in range(0, -n, -1):
            item['enable'] = gen_between(end + fade_out * (i - 1) / n, end + fade_out * i / n)
            item['fontcolor'] = orig_color + '@' + str(-i / n)
            filters.append(gen_filter('drawtext', item))

    tmp_index = 0
    for item in config['images']:
        animation_span = 0
        animation_x = 0
        animation_y = 0
        if 'animation_span' in item:
            animation_span = item['animation_span']
            del item['animation_span']
        if 'animation_x' in item:
            animation_x = item['animation_x']
            del item['animation_x']
        if 'animation_y' in item:
            animation_y = item['animation_y']
            del item['animation_y']

        if 'end' not in item:
            item['end'] = duration
        if 'start' not in item:
            item['start'] = 0

        animation_start = item['end'] -animation_span
        if item['start'] != 0:
            toffsets[len(inputs)] = item['start']
        item['enable'] = gen_between(item['start'], animation_start)
        del item['start']
        del item['end']
        inputs.append(item['source'])
        del item['source']
        index = len(inputs)
        filter_str = "[tmp%d][%d] %s [tmp%d]" % (tmp_index, index, gen_filter('overlay', item), tmp_index + 1)
        tmp_index += 1
        overlay_filters.append(filter_str)
        # animation
        n = animation_span * fps
        orig_x = item['x']
        orig_y = item['y']
        for i in range(0, n):
            item['x'] = orig_x + animation_x * i / n
            item['y'] = orig_y + animation_y * i / n
            item['enable'] = gen_between(animation_start + animation_span * i / n, animation_start + animation_span * (i+1) / n)
            filter_str = "[tmp%d][%d] %s [tmp%d]" % (tmp_index, index, gen_filter('overlay', item), tmp_index + 1)
            tmp_index += 1
            overlay_filters.append(filter_str)

    texts_filter_str = ','.join(filters)
    images_filter_str = ';'.join(overlay_filters)
    final_filter_str = '[0] copy [tmp0]; %s; [tmp%d] %s' % (images_filter_str, tmp_index, texts_filter_str)
    final_filter_str = final_filter_str.replace('; ; ', '; ')

    command_line = [ 'ffmpeg', '-i', input_file ]
    for i, item in enumerate(inputs):
        if i in toffsets:
            command_line += [ '-itsoffset', str(toffsets[i]) ]
        command_line += [ '-i', item ]
    command_line += [ '-filter_complex', final_filter_str, '-acodec', 'copy', output_file ]

    print(' '.join(command_line))

    subprocess.call(command_line)
