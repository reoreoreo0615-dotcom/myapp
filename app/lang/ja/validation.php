<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute を承認してください。',
    'accepted_if' => ':other が :value の場合、:attribute を承認してください。',
    'active_url' => ':attribute は有効なURLではありません。',
    'after' => ':attribute には :date より後の日付を指定してください。',
    'after_or_equal' => ':attribute には :date 以降の日付を指定してください。',
    'alpha' => ':attribute はアルファベットのみ使用できます。',
    'alpha_dash' => ':attribute は英数字とダッシュ(-)およびアンダースコア(_)が使用できます。',
    'alpha_num' => ':attribute は英数字のみ使用できます。',
    'array' => ':attribute は配列で指定してください。',
    'before' => ':attribute には :date より前の日付を指定してください。',
    'before_or_equal' => ':attribute には :date 以前の日付を指定してください。',
    'between' => [
        'array' => ':attribute は :min 個から :max 個までの間で指定してください。',
        'file' => ':attribute は :min から :max キロバイトの間で指定してください。',
        'numeric' => ':attribute は :min から :max の間で指定してください。',
        'string' => ':attribute は :min 文字から :max 文字の間で指定してください。',
    ],
    'boolean' => ':attribute は true か false を指定してください。',
    'confirmed' => ':attribute と確認用の値が一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attribute には有効な日付を指定してください。',
    'date_equals' => ':attribute には :date と同じ日付を指定してください。',
    'date_format' => ':attribute は :format 形式で指定してください。',
    'decimal' => ':attribute は小数点以下 :decimal 桁で指定してください。',
    'declined' => ':attribute を拒否してください。',
    'declined_if' => ':other が :value の場合、:attribute を拒否してください。',
    'different' => ':attribute と :other には、異なる値を指定してください。',
    'digits' => ':attribute は :digits 桁で指定してください。',
    'digits_between' => ':attribute は :min 桁から :max 桁の間で指定してください。',
    'dimensions' => ':attribute の画像サイズが無効です。',
    'distinct' => ':attribute には重複した値が含まれています。',
    'email' => ':attribute には有効なメールアドレスを指定してください。',
    'ends_with' => ':attribute には次の値のいずれかで終わる文字列を指定してください。: :values',
    'enum' => '選択された :attribute は無効です。',
    'exists' => '選択された :attribute は無効です。',
    'file' => ':attribute にはファイルを指定してください。',
    'filled' => ':attribute は必須です。',
    'gt' => [
        'array' => ':attribute には :value 個より多い項目を指定してください。',
        'file' => ':attribute には :value キロバイトより大きいファイルを指定してください。',
        'numeric' => ':attribute には :value より大きい値を指定してください。',
        'string' => ':attribute は :value 文字より長く指定してください。',
    ],
    'gte' => [
        'array' => ':attribute には :value 個以上の項目を指定してください。',
        'file' => ':attribute には :value キロバイト以上のファイルを指定してください。',
        'numeric' => ':attribute には :value 以上の値を指定してください。',
        'string' => ':attribute は :value 文字以上で指定してください。',
    ],
    'image' => ':attribute には画像を指定してください。',
    'in' => '選択された :attribute は無効です。',
    'in_array' => ':attribute には :other の値を指定してください。',
    'integer' => ':attribute には整数を指定してください。',
    'ip' => ':attribute には有効なIPアドレスを指定してください。',
    'ipv4' => ':attribute には有効なIPv4アドレスを指定してください。',
    'ipv6' => ':attribute には有効なIPv6アドレスを指定してください。',
    'json' => ':attribute には有効なJSON文字列を指定してください。',
    'lowercase' => ':attribute は小文字で指定してください。',
    'lt' => [
        'array' => ':attribute には :value 個より少ない項目を指定してください。',
        'file' => ':attribute には :value キロバイトより小さいファイルを指定してください。',
        'numeric' => ':attribute には :value より小さい値を指定してください。',
        'string' => ':attribute は :value 文字より短く指定してください。',
    ],
    'lte' => [
        'array' => ':attribute には :value 個以下の項目を指定してください。',
        'file' => ':attribute には :value キロバイト以下のファイルを指定してください。',
        'numeric' => ':attribute には :value 以下の値を指定してください。',
        'string' => ':attribute は :value 文字以下で指定してください。',
    ],
    'mac_address' => ':attribute には有効なMACアドレスを指定してください。',
    'max' => [
        'array' => ':attribute は :max 個以下指定してください。',
        'file' => ':attribute には :max キロバイト以下のファイルを指定してください。',
        'numeric' => ':attribute には :max 以下の数字を指定してください。',
        'string' => ':attribute は :max 文字以下で指定してください。',
    ],
    'max_digits' => ':attribute には :max 桁以下の数字を指定してください。',
    'mimes' => ':attribute には :values タイプのファイルを指定してください。',
    'mimetypes' => ':attribute には :values タイプのファイルを指定してください。',
    'min' => [
        'array' => ':attribute は :min 個以上指定してください。',
        'file' => ':attribute には :min キロバイト以上のファイルを指定してください。',
        'numeric' => ':attribute には :min 以上の数字を指定してください。',
        'string' => ':attribute は :min 文字以上で指定してください。',
    ],
    'min_digits' => ':attribute には :min 桁以上の数字を指定してください。',
    'missing' => ':attribute は存在してはいけません。',
    'missing_if' => ':other が :value の場合、:attribute は存在してはいけません。',
    'missing_unless' => ':other が :value でない場合、:attribute は存在してはいけません。',
    'missing_with' => ':values が存在する場合、:attribute は存在してはいけません。',
    'missing_with_all' => ':values が存在する場合、:attribute は存在してはいけません。',
    'multiple_of' => ':attribute には :value の倍数を指定してください。',
    'not_in' => '選択された :attribute は無効です。',
    'not_regex' => ':attribute の形式が無効です。',
    'numeric' => ':attribute には数字を指定してください。',
    'password' => [
        'letters' => ':attribute には英字を1文字以上含めてください。',
        'mixed' => ':attribute には大文字と小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attribute には数字を1文字以上含めてください。',
        'symbols' => ':attribute には記号を1文字以上含めてください。',
        'uncompromised' => '入力された :attribute は漏えいしたデータに含まれています。別の :attribute を選択してください。',
    ],
    'present' => ':attribute が存在していません。',
    'present_if' => ':other が :value の場合、:attribute が存在する必要があります。',
    'present_unless' => ':other が :value でない場合、:attribute が存在する必要があります。',
    'present_with' => ':values のいずれかが存在する場合、:attribute が存在する必要があります。',
    'present_with_all' => ':values が存在する場合、:attribute が存在する必要があります。',
    'prohibited' => ':attribute は指定できません。',
    'prohibited_if' => ':other が :value の場合、:attribute は指定できません。',
    'prohibited_unless' => ':other が :values のいずれかに含まれない場合、:attribute は指定できません。',
    'prohibits' => ':attribute は :other の入力を禁止しています。',
    'regex' => ':attribute の形式が無効です。',
    'required' => ':attribute は必須です。',
    'required_array_keys' => ':attribute には :values を含めてください。',
    'required_if' => ':other が :value の場合、:attribute は必須です。',
    'required_if_accepted' => ':other を承認する場合、:attribute は必須です。',
    'required_unless' => ':other が :values のいずれかに含まれない場合、:attribute は必須です。',
    'required_with' => ':values が指定されている場合、:attribute は必須です。',
    'required_with_all' => ':values が指定されている場合、:attribute は必須です。',
    'required_without' => ':values が指定されていない場合、:attribute は必須です。',
    'required_without_all' => ':values のいずれも指定されていない場合、:attribute は必須です。',
    'same' => ':attribute と :other には、同じ値を指定してください。',
    'size' => [
        'array' => ':attribute は :size 個指定してください。',
        'file' => ':attribute のファイルサイズは :size キロバイトでなければなりません。',
        'numeric' => ':attribute には :size を指定してください。',
        'string' => ':attribute は :size 文字で指定してください。',
    ],
    'starts_with' => ':attribute には次の値のいずれかで始まる文字列を指定してください。: :values',
    'string' => ':attribute は文字列を指定してください。',
    'timezone' => ':attribute には有効なタイムゾーンを指定してください。',
    'unique' => ':attribute はすでに使用されています。',
    'uploaded' => ':attribute のアップロードに失敗しました。',
    'uppercase' => ':attribute は大文字で指定してください。',
    'url' => ':attribute の形式が無効です。',
    'ulid' => ':attribute には有効なULIDを指定してください。',
    'uuid' => ':attribute には有効なUUIDを指定してください。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'email' => [
            'unique' => 'このメールアドレスはすでに登録されています。',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード(確認)',
        'current_password' => '現在のパスワード',
        'remember' => 'ログイン状態を保持する',
    ],

];
